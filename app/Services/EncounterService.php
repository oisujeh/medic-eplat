<?php

namespace App\Services;

use App\Enums\EncounterOutcome;
use App\Enums\EncounterStatus;
use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Events\EncounterSigned;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\QueueEntry;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The lifecycle of a clinical encounter: opened from a queue entry, drafted,
 * then signed — which disposes of the patient in one transaction.
 */
class EncounterService
{
    public function __construct(
        private readonly PatientFlowService $flow,
        private readonly BillingService $billing,
        private readonly AdmissionService $admissions,
        private readonly DomainEvents $events,
        private readonly WorkflowEngine $workflows,
    ) {}

    /**
     * Open the encounter for a queue entry, or resume the one already started.
     * Opening claims the patient for the acting staff member.
     */
    public function openForQueueEntry(QueueEntry $entry, User $actor): Encounter
    {
        return DB::transaction(function () use ($entry, $actor) {
            $entry->loadMissing('servicePoint');

            if ($entry->status === QueueStatus::Waiting) {
                $this->flow->call($entry, $actor);
            }

            return Encounter::firstOrCreate(
                ['queue_entry_id' => $entry->id],
                [
                    'patient_id' => $entry->patient_id,
                    'visit_id' => $entry->visit_id,
                    'service_point_id' => $entry->service_point_id,
                    'author_id' => $actor->id,
                    'entered_by' => $actor->id,
                    'entered_at' => now(),
                    'encounter_at' => now(),
                    'type' => $this->workflows->encounterTypeFor($entry),
                    'status' => EncounterStatus::InProgress,
                    'started_at' => now(),
                ],
            );
        });
    }

    /**
     * The clinical date a signed note carries. A date earlier than today is
     * retrospective entry from paper and needs the backdating permission
     * (PRD ENC-03); a date in the future is never accepted.
     *
     * @throws ValidationException
     */
    private function resolveEncounterDate(Encounter $encounter, User $actor, ?CarbonInterface $encounterAt): CarbonInterface
    {
        $default = $encounter->encounter_at ?? $encounter->started_at ?? now();

        if ($encounterAt === null) {
            return $default;
        }

        if ($encounterAt->isFuture()) {
            throw ValidationException::withMessages(['encounter_at' => 'The encounter date cannot be in the future.']);
        }

        $backdated = $encounterAt->toDateString() < now()->toDateString();

        if ($backdated && ! $actor->hasPermission(Permission::BackdateEncounters)) {
            throw ValidationException::withMessages(['encounter_at' => 'You do not have permission to backdate encounters. Ask a records officer to enter this note.']);
        }

        return $encounterAt;
    }

    /**
     * Save documentation without signing.
     *
     * @param  array<string, mixed>  $narrative
     */
    public function saveDraft(Encounter $encounter, array $narrative): Encounter
    {
        $encounter->update($this->narrativeOnly($narrative));

        return $encounter;
    }

    /**
     * Sign the encounter off and dispose of the patient: post the consultation
     * fee, order an admission when the outcome calls for one, and complete
     * the queue entry — routing the patient onward when asked. All or nothing.
     *
     * @param  array<string, mixed>  $narrative
     */
    public function sign(
        Encounter $encounter,
        User $actor,
        array $narrative,
        ?ServicePoint $next = null,
        Priority $nextPriority = Priority::Normal,
        ?string $nextNote = null,
        ?User $nextAssignedTo = null,
        ?User $provider = null,
        ?CarbonInterface $encounterAt = null,
    ): Encounter {
        $encounter->loadMissing('visit', 'servicePoint');

        if ($next !== null && ! $this->workflows->allows($encounter->visit, $encounter->servicePoint, $next)) {
            throw ValidationException::withMessages([
                'next_service_point_id' => "{$next->name} does not follow {$encounter->servicePoint?->name} on this patient's pathway.",
            ]);
        }

        // A clerk entering a paper note names the clinician who saw the
        // patient; the clerk is recorded separately as the data-entry user.
        if ($provider !== null && ! $provider->isProvider()) {
            throw ValidationException::withMessages([
                'provider_id' => "{$provider->name} is not a clinical cadre and cannot be named as the provider.",
            ]);
        }

        $encounterAt = $this->resolveEncounterDate($encounter, $actor, $encounterAt);

        return DB::transaction(function () use ($encounter, $actor, $narrative, $next, $nextPriority, $nextNote, $nextAssignedTo, $provider, $encounterAt) {
            $encounter->fill($this->narrativeOnly($narrative));
            $encounter->fill([
                'author_id' => $provider?->id ?? $actor->id,
                'entered_by' => $actor->id,
                'entered_at' => now(),
                'encounter_at' => $encounterAt,
                'status' => EncounterStatus::Signed,
                'signed_at' => now(),
            ]);
            $encounter->save();

            $this->events->publish(new EncounterSigned($encounter, $actor));

            if ($encounter->type->isConsultation() && $encounter->outcome === EncounterOutcome::Admit) {
                $this->admissions->requestFromEncounter($encounter, $actor);
            }

            $this->postStageFee($encounter, $actor);

            $entry = $encounter->queueEntry;

            if ($entry && $entry->status->isActive()) {
                $this->flow->complete(
                    entry: $entry,
                    actor: $actor,
                    next: $next,
                    nextPriority: $nextPriority,
                    nextNote: $nextNote,
                    nextAssignedTo: $nextAssignedTo,
                );
            }

            return $encounter->refresh();
        });
    }

    /**
     * Post the fee the pathway step carries (the consultation fee where no
     * pathway says otherwise) from the fee schedule to the visit's bill.
     */
    private function postStageFee(Encounter $encounter, User $actor): void
    {
        $code = $this->workflows->chargeCodeFor($encounter);

        if ($code === null || ! $encounter->visit) {
            return;
        }

        $charge = ServiceCharge::where('code', $code)->active()->first();

        if ($charge === null || (float) $charge->price <= 0) {
            return;
        }

        $fee = $charge->price;
        $encounter->loadMissing('servicePoint');

        $this->billing->postCharge(
            bill: $this->billing->openBillFor($encounter->patient, $encounter->visit),
            source: BillCharge::SOURCE_CONSULTATION,
            description: $charge->name.' — '.($encounter->servicePoint->name ?? $encounter->type->label()),
            quantity: 1,
            unitPrice: (float) $fee,
            actor: $actor,
            reference: $encounter,
            charge: $charge,
        );
    }

    /**
     * Keep only the documented fields, so disposition inputs never reach the
     * model.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function narrativeOnly(array $data): array
    {
        return array_intersect_key($data, array_flip([...Encounter::NARRATIVE, 'outcome', 'follow_up_at']));
    }
}

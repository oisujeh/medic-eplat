<?php

namespace App\Services;

use App\Enums\EncounterOutcome;
use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\QueueEntry;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
                    'type' => EncounterType::forServicePoint(
                        (string) $entry->servicePoint?->module_slug,
                        (string) $entry->servicePoint?->slug,
                    ),
                    'status' => EncounterStatus::InProgress,
                    'started_at' => now(),
                ],
            );
        });
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
    ): Encounter {
        return DB::transaction(function () use ($encounter, $actor, $narrative, $next, $nextPriority, $nextNote, $nextAssignedTo) {
            $encounter->fill($this->narrativeOnly($narrative));
            $encounter->fill([
                'author_id' => $actor->id,
                'status' => EncounterStatus::Signed,
                'signed_at' => now(),
            ]);
            $encounter->save();

            if ($encounter->type->isConsultation()) {
                if ($encounter->outcome === EncounterOutcome::Admit) {
                    $this->admissions->requestFromEncounter($encounter, $actor);
                }

                $this->postConsultationFee($encounter, $actor);
            }

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
     * Post the consultation fee from the fee schedule to the visit's bill.
     */
    private function postConsultationFee(Encounter $encounter, User $actor): void
    {
        $fee = ServiceCharge::where('code', ServiceCharge::CONSULTATION)->active()->value('price');

        if (! $fee || ! $encounter->visit) {
            return;
        }

        $encounter->loadMissing('servicePoint');

        $this->billing->postCharge(
            bill: $this->billing->openBillFor($encounter->patient, $encounter->visit),
            source: BillCharge::SOURCE_CONSULTATION,
            description: 'Consultation — '.($encounter->servicePoint?->name ?? $encounter->type->label()),
            quantity: 1,
            unitPrice: (float) $fee,
            actor: $actor,
            reference: $encounter,
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

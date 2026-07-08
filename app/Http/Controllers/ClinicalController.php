<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Http\Requests\CompleteConsultationRequest;
use App\Http\Requests\SaveEncounterRequest;
use App\Models\Encounter;
use App\Models\QueueEntry;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\PatientFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalController extends Controller
{
    /** The module that governs clinical service points. */
    private const MODULE = 'clinical';

    public function __construct(private readonly PatientFlowService $flow) {}

    /**
     * The clinician console: patients waiting at clinical service points, plus
     * the clinician's recently completed consultations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $seesAll = $user->hasFullModuleAccess();

        $entries = QueueEntry::query()
            ->whereIn('service_point_id', $this->clinicalServicePointIds())
            ->active()
            ->when(! $seesAll, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('assigned_to')
                ->orWhere('assigned_to', $user->id)))
            ->worklistOrder()
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'servicePoint:id,name',
                'assignedTo:id,name',
                'visit:id,visit_number',
                'visit.vitalSigns.recordedBy:id,name',
            ])
            ->get()
            ->map(fn (QueueEntry $entry) => [
                'id' => $entry->id,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'priority' => $entry->priority->value,
                'priority_label' => $entry->priority->label(),
                'service_point' => $entry->servicePoint->name,
                'assigned_to' => $entry->assignedTo?->name,
                'assigned_to_me' => $entry->assigned_to === $user->id,
                'waiting_since' => $entry->queued_at?->diffForHumans(),
                'latest_vitals' => $entry->visit?->vitalSigns->first()?->summary(),
                'consult_url' => route('clinical.consult', $entry),
                'patient' => [
                    'name' => $entry->patient->fullName(),
                    'initials' => $entry->patient->initials(),
                    'file_number' => $entry->patient->file_number,
                    'sex' => $entry->patient->sex,
                    'age' => $entry->patient->age(),
                ],
            ]);

        $recent = Encounter::query()
            ->where('clinician_id', $user->id)
            ->where('status', Encounter::STATUS_COMPLETED)
            ->latest('completed_at')
            ->take(8)
            ->with('patient:id,file_number,surname,first_name,other_names')
            ->get()
            ->map(fn (Encounter $e) => [
                'id' => $e->id,
                'patient_name' => $e->patient->fullName(),
                'file_number' => $e->patient->file_number,
                'diagnosis' => $e->diagnosis,
                'completed_at' => $e->completed_at?->diffForHumans(),
                'patient_url' => route('patients.show', $e->patient_id),
            ]);

        return Inertia::render('clinical/Index', [
            'queue' => $entries,
            'seesAll' => $seesAll,
            'recent' => $recent,
        ]);
    }

    /**
     * The consultation screen for a queue entry — opening it claims the patient.
     */
    public function consult(Request $request, QueueEntry $entry): Response
    {
        $this->authorizeClinical($request, $entry);

        // Claim the patient the moment the clinician opens the consultation.
        if ($entry->status->value === 'waiting') {
            $this->flow->call($entry, $request->user());
            $entry->refresh();
        }

        $encounter = $this->encounterFor($entry, $request->user());
        $entry->load(['patient', 'servicePoint:id,name', 'visit:id,visit_number']);

        $patient = $entry->patient;

        return Inertia::render('clinical/Consultation', [
            'entry' => [
                'id' => $entry->id,
                'service_point' => $entry->servicePoint->name,
                'visit_number' => $entry->visit?->visit_number,
            ],
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->fullName(),
                'initials' => $patient->initials(),
                'file_number' => $patient->file_number,
                'sex_label' => $patient->sex === 'F' ? 'Female' : 'Male',
                'age' => $patient->age(),
                'url' => route('patients.show', $patient->id),
            ],
            'vitals' => $entry->visit?->vitalSigns()->with('recordedBy:id,name')->first()?->summary(),
            'encounter' => [
                'presenting_complaint' => $encounter->presenting_complaint,
                'history' => $encounter->history,
                'examination' => $encounter->examination,
                'diagnosis' => $encounter->diagnosis,
                'plan' => $encounter->plan,
                'status' => $encounter->status,
            ],
            'pastEncounters' => $this->pastEncounters($entry),
            'onwardServicePoints' => $this->onwardServicePoints(),
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    /**
     * Save the consultation as a draft.
     */
    public function save(SaveEncounterRequest $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeClinical($request, $entry);

        $this->encounterFor($entry, $request->user())->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Consultation saved.']);

        return back();
    }

    /**
     * Sign off the consultation and dispose of the patient.
     */
    public function complete(CompleteConsultationRequest $request, QueueEntry $entry): RedirectResponse
    {
        $this->authorizeClinical($request, $entry);
        abort_unless($entry->status->isActive(), 422);

        $encounter = $this->encounterFor($entry, $request->user());
        $encounter->update([
            ...$request->safe()->only(['presenting_complaint', 'history', 'examination', 'diagnosis', 'plan']),
            'status' => Encounter::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $next = $request->filled('next_service_point_id')
            ? ServicePoint::find($request->integer('next_service_point_id'))
            : null;

        $nextAssignee = $next && $request->filled('next_assigned_to')
            ? $next->eligiblePersonnel()->firstWhere('id', $request->integer('next_assigned_to'))
            : null;

        $this->flow->complete(
            entry: $entry,
            actor: $request->user(),
            next: $next,
            nextPriority: Priority::from($request->input('next_priority', Priority::Normal->value)),
            nextNote: $request->input('next_note'),
            nextAssignedTo: $nextAssignee,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $next
                ? "Consultation completed — routed to {$next->name}."
                : 'Consultation completed.',
        ]);

        return to_route('clinical.index');
    }

    /**
     * Find or start the encounter tied to a queue entry.
     */
    private function encounterFor(QueueEntry $entry, User $clinician): Encounter
    {
        return Encounter::firstOrCreate(
            ['queue_entry_id' => $entry->id],
            [
                'visit_id' => $entry->visit_id,
                'patient_id' => $entry->patient_id,
                'clinician_id' => $clinician->id,
                'status' => Encounter::STATUS_OPEN,
                'started_at' => now(),
            ],
        );
    }

    /**
     * Prior completed encounters for the patient (excluding the current entry).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pastEncounters(QueueEntry $entry): \Illuminate\Support\Collection
    {
        return Encounter::query()
            ->where('patient_id', $entry->patient_id)
            ->where('status', Encounter::STATUS_COMPLETED)
            ->where('queue_entry_id', '!=', $entry->id)
            ->latest('completed_at')
            ->take(10)
            ->with('clinician:id,name')
            ->get()
            ->map(fn (Encounter $e) => [
                'id' => $e->id,
                'diagnosis' => $e->diagnosis,
                'presenting_complaint' => $e->presenting_complaint,
                'plan' => $e->plan,
                'clinician' => $e->clinician?->name,
                'completed_at' => $e->completed_at?->isoFormat('D MMM YYYY, h:mm a'),
            ]);
    }

    /**
     * Active service points a patient can be disposed to, with their staff.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function onwardServicePoints(): \Illuminate\Support\Collection
    {
        return ServicePoint::active()
            ->get()
            ->map(fn (ServicePoint $sp) => [
                'id' => $sp->id,
                'name' => $sp->name,
                'personnel' => $sp->eligiblePersonnel()
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
            ]);
    }

    /**
     * Ensure the entry is a clinical one visible to the acting clinician.
     */
    private function authorizeClinical(Request $request, QueueEntry $entry): void
    {
        $user = $request->user();
        $entry->loadMissing('servicePoint');

        abort_unless($entry->servicePoint->module_slug === self::MODULE, 404);

        abort_unless(
            $user->hasFullModuleAccess()
                || $entry->assigned_to === null
                || $entry->assigned_to === $user->id,
            403,
        );
    }

    /**
     * IDs of active service points governed by the clinical module.
     *
     * @return array<int, int>
     */
    private function clinicalServicePointIds(): array
    {
        return ServicePoint::active()
            ->where('module_slug', self::MODULE)
            ->pluck('id')
            ->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentSource;
use App\Enums\EncounterOutcome;
use App\Enums\ObservationCode;
use App\Enums\Priority;
use App\Http\Requests\BookFollowUpRequest;
use App\Http\Requests\SaveEncounterRequest;
use App\Http\Requests\SignEncounterRequest;
use App\Http\Requests\StoreAddendumRequest;
use App\Http\Resources\AllergyResource;
use App\Http\Resources\EncounterAddendumResource;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\EncounterSummaryResource;
use App\Http\Resources\ImmunizationResource;
use App\Http\Resources\LabResultResource;
use App\Http\Resources\LabTestResource;
use App\Http\Resources\MedicationResource;
use App\Http\Resources\ObservationSetResource;
use App\Http\Resources\PatientAlertResource;
use App\Http\Resources\PatientBannerResource;
use App\Http\Resources\ProblemResource;
use App\Http\Resources\ServicePointOptionResource;
use App\Models\Encounter;
use App\Models\LabTest;
use App\Models\ServicePoint;
use App\Services\AppointmentService;
use App\Services\CaseSurveillance;
use App\Services\EncounterService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The encounter screen and its lifecycle: draft, sign, follow-up.
 */
class EncounterController extends Controller
{
    public function __construct(
        private readonly EncounterService $encounters,
        private readonly AppointmentService $appointments,
        private readonly CaseSurveillance $surveillance,
    ) {}

    /**
     * The documentation screen for an encounter, with the patient's clinical
     * record alongside.
     */
    public function show(Request $request, Encounter $encounter): Response
    {
        abort_unless($request->user()->can('view', $encounter), 403);

        $encounter->load([
            'servicePoint:id,name,slug,captures_vitals',
            'visit:id,visit_number,opened_at',
            'author:id,name',
            'immunizations.administeredBy:id,name',
            'addenda.author:id,name',
        ]);

        $patient = $encounter->patient;
        $patient->load([
            'allergies' => fn ($q) => $q->active(),
            'problems' => fn ($q) => $q->open(),
            'medications' => fn ($q) => $q->active(),
            'labResults' => fn ($q) => $q->reorder()->latest()->take(12),
            'alerts' => fn ($q) => $q->active(),
        ]);

        $observationSets = $encounter->visit
            ? $encounter->visit->observationSets()->with('observations', 'recordedBy:id,name')->take(10)->get()
            : collect();

        return Inertia::render('encounters/Show', [
            'encounter' => EncounterResource::make($encounter),
            'patient' => PatientBannerResource::make($patient),
            'allergies' => AllergyResource::collection($patient->allergies),
            'problems' => ProblemResource::collection($patient->problems),
            'medications' => MedicationResource::collection($patient->medications),
            'labResults' => LabResultResource::collection($patient->labResults),
            'alerts' => PatientAlertResource::collection($patient->alerts),
            'surveillanceCases' => $this->surveillance->bannerFor($patient),
            'immunizations' => ImmunizationResource::collection($encounter->immunizations),
            'addenda' => EncounterAddendumResource::collection($encounter->addenda),
            'observationSets' => ObservationSetResource::collection($observationSets),
            'observationCodes' => ObservationCode::definitions(),
            'labCatalog' => $encounter->type->isConsultation()
                ? LabTestResource::collection(LabTest::active()->withCount('components')->get())
                : [],
            'pastEncounters' => EncounterSummaryResource::collection($this->pastEncounters($encounter)),
            'onwardServicePoints' => ServicePointOptionResource::collection(ServicePoint::active()->get()),
            'priorities' => collect(Priority::cases())->map(fn (Priority $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'outcomes' => EncounterOutcome::options(),
            'can' => [
                'document' => $request->user()->can('document', $encounter),
                'sign' => $request->user()->can('sign', $encounter),
                'addend' => $request->user()->can('addend', $encounter),
            ],
        ]);
    }

    /**
     * Save the documentation as a draft.
     */
    public function update(SaveEncounterRequest $request, Encounter $encounter): RedirectResponse
    {
        $this->encounters->saveDraft($encounter, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft saved.']);

        return back();
    }

    /**
     * Sign the encounter off and dispose of the patient.
     */
    public function sign(SignEncounterRequest $request, Encounter $encounter): RedirectResponse
    {
        $next = $request->filled('next_service_point_id')
            ? ServicePoint::find($request->integer('next_service_point_id'))
            : null;

        $nextAssignee = $next && $request->filled('next_assigned_to')
            ? $next->eligiblePersonnel()->firstWhere('id', $request->integer('next_assigned_to'))
            : null;

        $this->encounters->sign(
            encounter: $encounter,
            actor: $request->user(),
            narrative: $request->validated(),
            next: $next,
            nextPriority: Priority::from($request->input('next_priority', Priority::Normal->value)),
            nextNote: $request->input('next_note'),
            nextAssignedTo: $nextAssignee,
        );

        $label = $encounter->type->label();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $next ? "{$label} signed — routed to {$next->name}." : "{$label} signed.",
        ]);

        return $encounter->type->isConsultation() ? to_route('clinical.index') : to_route('nursing.index');
    }

    /**
     * Book a follow-up appointment from the encounter. The appointment is
     * linked to this encounter and left for the clinic to assign a provider.
     */
    public function bookFollowUp(BookFollowUpRequest $request, Encounter $encounter): RedirectResponse
    {
        $data = $request->validated();

        $this->appointments->book(
            patient: $encounter->patient,
            servicePoint: ServicePoint::query()->findOrFail((int) $data['service_point_id']),
            start: Carbon::parse($data['scheduled_start']),
            durationMinutes: $data['duration_minutes'] ?? 30,
            actor: $request->user(),
            source: AppointmentSource::FollowUp,
            reason: $data['reason'] ?? 'Follow-up',
            encounterId: $encounter->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up appointment booked.']);

        return back();
    }

    /**
     * Append an addendum to a signed encounter. The signed note itself is
     * never changed.
     */
    public function addend(StoreAddendumRequest $request, Encounter $encounter): RedirectResponse
    {
        $encounter->addenda()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'recorded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Addendum added.']);

        return back();
    }

    /**
     * The patient's other signed encounters, newest first.
     *
     * @return Collection<int, Encounter>
     */
    private function pastEncounters(Encounter $current): Collection
    {
        return Encounter::query()
            ->where('patient_id', $current->patient_id)
            ->whereKeyNot($current->id)
            ->signed()
            ->latest('signed_at')
            ->take(10)
            ->with(['author:id,name', 'servicePoint:id,name', 'codedDiagnoses', 'addenda.author:id,name'])
            ->get();
    }
}

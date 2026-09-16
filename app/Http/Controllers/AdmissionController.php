<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionStatus;
use App\Enums\DischargeType;
use App\Enums\ObservationCode;
use App\Enums\WardType;
use App\Http\Requests\AssignBedRequest;
use App\Http\Requests\DischargeAdmissionRequest;
use App\Http\Requests\StoreAdmissionNoteRequest;
use App\Http\Requests\StoreAdmissionRequest;
use App\Http\Requests\TransferAdmissionRequest;
use App\Http\Resources\ObservationSetResource;
use App\Models\Admission;
use App\Models\AdmissionMovement;
use App\Models\AdmissionNote;
use App\Models\Bed;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Services\AdmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admissions console: ward occupancy, patients awaiting a bed, current
 * inpatients and the inpatient record itself.
 */
class AdmissionController extends Controller
{
    public function __construct(private readonly AdmissionService $admissions) {}

    /**
     * Ward overview, admission orders awaiting a bed, and current inpatients.
     */
    public function index(Request $request): Response
    {
        $wards = Ward::query()->with('beds')->orderBy('sort_order')->orderBy('name')->get();

        // Arriving from a patient profile pre-fills the admit dialog.
        $preselected = $request->filled('patient_id')
            ? Patient::find($request->integer('patient_id'))
            : null;

        $pending = Admission::query()
            ->where('status', AdmissionStatus::Pending->value)
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'ward:id,name',
                'requestedBy:id,name',
                'attending:id,name',
            ])
            ->oldest('created_at')
            ->get();

        $inpatients = Admission::query()
            ->where('status', AdmissionStatus::Admitted->value)
            ->with([
                'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth',
                'ward:id,name,sort_order',
                'bed:id,label,sort_order',
                'attending:id,name',
            ])
            ->get()
            ->sortBy([
                fn (Admission $a, Admission $b) => ($a->ward?->sort_order ?? 0) <=> ($b->ward?->sort_order ?? 0),
                fn (Admission $a, Admission $b) => ($a->bed?->sort_order ?? 0) <=> ($b->bed?->sort_order ?? 0),
            ])
            ->values();

        return Inertia::render('admissions/Index', [
            'wards' => $wards->map(fn (Ward $ward) => $this->wardCard($ward)),
            'pending' => $pending->map(fn (Admission $a) => [
                'id' => $a->id,
                'admission_number' => $a->admission_number,
                'diagnosis' => $a->admitting_diagnosis,
                'requested_ward' => $a->ward?->name,
                'requested_ward_id' => $a->ward_id,
                'requested_by' => $a->requestedBy?->name,
                'requested_diff' => $a->created_at?->diffForHumans(),
                'attending' => $a->attending?->name,
                'attending_id' => $a->attending_id,
                'url' => route('admissions.show', $a),
                'patient' => $this->patientCard($a->patient),
            ]),
            'inpatients' => $inpatients->map(fn (Admission $a) => [
                'id' => $a->id,
                'admission_number' => $a->admission_number,
                'diagnosis' => $a->admitting_diagnosis,
                'ward' => $a->ward?->name,
                'ward_id' => $a->ward_id,
                'bed' => $a->bed?->label,
                'attending' => $a->attending?->name,
                'admitted_at' => $a->admitted_at?->isoFormat('D MMM, HH:mm'),
                'admitted_diff' => $a->admitted_at?->diffForHumans(),
                'days' => $a->lengthOfStayDays(),
                'url' => route('admissions.show', $a),
                'patient' => $this->patientCard($a->patient),
            ]),
            'clinicians' => $this->clinicians(),
            'wardTypes' => WardType::options(),
            'bedCharges' => WardController::bedCharges(),
            'preselected' => $preselected ? $this->patientCard($preselected) : null,
        ]);
    }

    /**
     * Patient lookup for the admit dialog.
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());

        $patients = Patient::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('other_names', 'like', "%{$q}%")))
            ->orderBy('surname')
            ->limit(10)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->fullName(),
                'file_number' => $p->file_number,
                'sex' => $p->sex,
                'age' => $p->age(),
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Order an admission, placing the patient straight away when a bed is
     * chosen.
     */
    public function store(StoreAdmissionRequest $request): RedirectResponse
    {
        $patient = Patient::findOrFail($request->integer('patient_id'));
        $attending = $request->filled('attending_id') ? User::find($request->integer('attending_id')) : null;

        $admission = $this->admissions->request(
            patient: $patient,
            actor: $request->user(),
            diagnosis: $request->string('admitting_diagnosis')->value(),
            reason: $request->input('reason'),
            ward: $request->filled('ward_id') ? Ward::find($request->integer('ward_id')) : null,
            attending: $attending,
        );

        if ($request->filled('bed_id')) {
            $this->admissions->admit($admission, Bed::findOrFail($request->integer('bed_id')), $request->user(), $attending);

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->fullName()} admitted."]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Admission ordered for {$patient->fullName()}. Assign a bed when one is ready."]);
        }

        return to_route('admissions.show', $admission);
    }

    /**
     * The inpatient record.
     */
    public function show(Request $request, Admission $admission): Response
    {
        $admission->load([
            'patient',
            'ward:id,name',
            'bed:id,label',
            'requestedBy:id,name',
            'admittedBy:id,name',
            'attending:id,name',
            'dischargedBy:id,name',
            'movements.fromWard:id,name',
            'movements.fromBed:id,label',
            'movements.toWard:id,name',
            'movements.toBed:id,label',
            'movements.movedBy:id,name',
            'notes.author:id,name',
            'observationSets.observations',
            'observationSets.recordedBy:id,name',
        ]);

        $bill = $admission->visit_id
            ? Bill::query()->where('visit_id', $admission->visit_id)->open()->first()
            : null;

        return Inertia::render('admissions/Show', [
            'admission' => [
                'id' => $admission->id,
                'admission_number' => $admission->admission_number,
                'status' => $admission->status->value,
                'status_label' => $admission->status->label(),
                'tone' => $admission->status->tone(),
                'is_active' => $admission->isActive(),
                'diagnosis' => $admission->admitting_diagnosis,
                'reason' => $admission->reason,
                'ward' => $admission->ward?->name,
                'ward_id' => $admission->ward_id,
                'bed' => $admission->bed?->label,
                'bed_id' => $admission->bed_id,
                'requested_by' => $admission->requestedBy?->name,
                'requested_at' => $admission->created_at?->isoFormat('D MMM YYYY, h:mm a'),
                'admitted_by' => $admission->admittedBy?->name,
                'admitted_at' => $admission->admitted_at?->isoFormat('D MMM YYYY, h:mm a'),
                'admitted_diff' => $admission->admitted_at?->diffForHumans(),
                'attending' => $admission->attending?->name,
                'attending_id' => $admission->attending_id,
                'days' => $admission->lengthOfStayDays(),
                'discharged_by' => $admission->dischargedBy?->name,
                'discharged_at' => $admission->discharged_at?->isoFormat('D MMM YYYY, h:mm a'),
                'discharge_type' => $admission->discharge_type?->value,
                'discharge_type_label' => $admission->discharge_type?->label(),
                'discharge_summary' => $admission->discharge_summary,
                'follow_up_at' => $admission->follow_up_at?->isoFormat('D MMM YYYY'),
                'cancel_reason' => $admission->cancel_reason,
                'cancelled_at' => $admission->cancelled_at?->isoFormat('D MMM YYYY, h:mm a'),
            ],
            'patient' => [
                ...$this->patientCard($admission->patient),
                'phone' => $admission->patient->phone,
                'coverage_label' => $admission->patient->coverage === 'hmo'
                    ? ($admission->patient->hmo_name ?: 'HMO')
                    : 'Private',
            ],
            'movements' => $admission->movements->map(fn (AdmissionMovement $m) => [
                'id' => $m->id,
                'from' => $m->fromWard ? "{$m->fromWard->name} · {$m->fromBed?->label}" : null,
                'to' => $m->toWard ? "{$m->toWard->name} · {$m->toBed?->label}" : null,
                'reason' => $m->reason,
                'moved_by' => $m->movedBy?->name,
                'moved_at' => $m->moved_at->isoFormat('D MMM YYYY, h:mm a'),
            ]),
            'notes' => $admission->notes->map(fn (AdmissionNote $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'type_label' => $n->typeLabel(),
                'note' => $n->note,
                'author' => $n->author?->name,
                'recorded_at' => $n->recorded_at->isoFormat('D MMM YYYY, h:mm a'),
                'recorded_diff' => $n->recorded_at->diffForHumans(),
            ]),
            'observationSets' => ObservationSetResource::collection($admission->observationSets),
            'observationsUrl' => route('patients.observations.store', $admission->patient_id),
            'bill' => $bill ? [
                'total' => $bill->total(),
                'paid' => $bill->paidTotal(),
                'balance' => $bill->balance(),
                'url' => $request->user()->canAccessModule('billing') ? route('billing.show', $bill) : null,
            ] : null,
            'wards' => Ward::query()->active()->with('beds')->get()->map(fn (Ward $ward) => $this->wardCard($ward)),
            'clinicians' => $this->clinicians(),
            'noteTypes' => collect(AdmissionNote::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'observationCodes' => ObservationCode::definitions(),
            'dischargeTypes' => DischargeType::options(),
        ]);
    }

    /**
     * Place a waiting patient in a bed.
     */
    public function assign(AssignBedRequest $request, Admission $admission): RedirectResponse
    {
        $attending = $request->filled('attending_id') ? User::find($request->integer('attending_id')) : null;

        $this->admissions->admit($admission, Bed::findOrFail($request->integer('bed_id')), $request->user(), $attending);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient admitted to bed.']);

        return back();
    }

    /**
     * Move an inpatient to another bed.
     */
    public function transfer(TransferAdmissionRequest $request, Admission $admission): RedirectResponse
    {
        $this->admissions->transfer($admission, Bed::findOrFail($request->integer('bed_id')), $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient transferred.']);

        return back();
    }

    /**
     * Discharge an inpatient.
     */
    public function discharge(DischargeAdmissionRequest $request, Admission $admission): RedirectResponse
    {
        $this->admissions->discharge(
            admission: $admission,
            actor: $request->user(),
            type: DischargeType::from($request->string('discharge_type')->value()),
            summary: $request->input('discharge_summary'),
            followUp: $request->filled('follow_up_at') ? Carbon::parse($request->input('follow_up_at')) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Patient discharged.']);

        return back();
    }

    /**
     * Withdraw an admission order that never reached a bed.
     */
    public function cancel(Request $request, Admission $admission): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->admissions->cancel($admission, $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admission order cancelled.']);

        return back();
    }

    /**
     * Add a ward note.
     */
    public function storeNote(StoreAdmissionNoteRequest $request, Admission $admission): RedirectResponse
    {
        $this->admissions->addNote($admission, $request->user(), $request->string('type')->value(), $request->string('note')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note saved.']);

        return back();
    }

    /**
     * A ward with its occupancy and the beds still free.
     *
     * @return array<string, mixed>
     */
    private function wardCard(Ward $ward): array
    {
        return [
            'id' => $ward->id,
            'name' => $ward->name,
            'code' => $ward->code,
            'type' => $ward->type->value,
            'type_label' => $ward->type->label(),
            'is_active' => $ward->is_active,
            'url' => route('admissions.wards.show', $ward),
            ...$ward->occupancy(),
            'available_beds' => $ward->beds
                ->filter(fn (Bed $bed) => $bed->isAvailable())
                ->map(fn (Bed $bed) => ['id' => $bed->id, 'label' => $bed->label])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patientCard(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'name' => $patient->fullName(),
            'initials' => $patient->initials(),
            'file_number' => $patient->file_number,
            'sex' => $patient->sex,
            'age' => $patient->age(),
            'url' => route('patients.show', $patient),
        ];
    }

    /**
     * Staff who can be named as the attending clinician.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function clinicians(): Collection
    {
        return User::query()
            ->whereNull('deactivated_at')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['physician', 'chief-medical-director']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }
}

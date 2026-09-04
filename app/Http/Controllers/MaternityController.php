<?php

namespace App\Http\Controllers;

use App\Enums\BirthOutcome;
use App\Enums\DeliveryMode;
use App\Enums\EncounterType;
use App\Enums\MaternalOutcome;
use App\Enums\ObservationCode;
use App\Http\Requests\StorePregnancyRequest;
use App\Models\Birth;
use App\Models\Delivery;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\User;
use App\Services\MaternityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The maternity desk: the antenatal register, each pregnancy's record, and
 * the deliveries that close them.
 */
class MaternityController extends Controller
{
    public function __construct(private readonly MaternityService $maternity) {}

    /**
     * The antenatal register with headline figures and recent deliveries.
     */
    public function index(Request $request): Response
    {
        $active = Pregnancy::query()
            ->active()
            ->with('patient:id,file_number,surname,first_name,other_names,sex,date_of_birth,phone')
            ->withCount(['ancVisits' => fn ($q) => $q->whereColumn('encounters.signed_at', '>=', 'pregnancies.created_at')])
            ->orderByRaw('edd is null')
            ->orderBy('edd')
            ->get();

        $lastAnc = Encounter::query()
            ->ofType(EncounterType::Nursing)
            ->atServicePoint('anc')
            ->signed()
            ->whereIn('patient_id', $active->pluck('patient_id'))
            ->selectRaw('patient_id, max(signed_at) as last_at')
            ->groupBy('patient_id')
            ->pluck('last_at', 'patient_id');

        $monthStart = now()->startOfMonth();
        $deliveriesThisMonth = Delivery::query()->where('delivered_at', '>=', $monthStart)->withCount('births')->get();

        $preselected = $request->filled('patient_id') ? Patient::find($request->integer('patient_id')) : null;

        return Inertia::render('maternity/Index', [
            'pregnancies' => $active->map(fn (Pregnancy $p) => [
                'id' => $p->id,
                'pregnancy_number' => $p->pregnancy_number,
                'patient' => $this->patientCard($p->patient),
                'gravida' => $p->gravida,
                'para' => $p->para,
                'edd' => $p->edd?->isoFormat('D MMM YYYY'),
                'edd_diff' => $p->edd?->diffForHumans(),
                'ga_weeks' => $p->gestationalAgeWeeks(),
                'overdue' => $p->isOverdue(),
                'due_soon' => $p->edd !== null && ! $p->isOverdue() && $p->edd->lte(now()->addDays(30)),
                'booking_date' => $p->booking_date?->isoFormat('D MMM YYYY'),
                'risk_factors' => $p->risk_factors ?? [],
                'anc_visits' => $p->anc_visits_count,
                'last_anc' => ($at = $lastAnc->get($p->patient_id)) ? Carbon::parse($at)->diffForHumans() : null,
                'url' => route('maternity.show', $p),
            ]),
            'stats' => [
                'active' => $active->count(),
                'due_soon' => $active->filter(fn (Pregnancy $p) => $p->edd !== null && ! $p->isOverdue() && $p->edd->lte(now()->addDays(30)))->count(),
                'overdue' => $active->filter(fn (Pregnancy $p) => $p->isOverdue())->count(),
                'high_risk' => $active->filter(fn (Pregnancy $p) => ($p->risk_factors ?? []) !== [])->count(),
                'deliveries_month' => $deliveriesThisMonth->count(),
                'live_births_month' => Birth::query()
                    ->whereIn('delivery_id', $deliveriesThisMonth->pluck('id'))
                    ->where('outcome', BirthOutcome::Live->value)
                    ->count(),
            ],
            'recentDeliveries' => Delivery::query()
                ->with(['patient:id,file_number,surname,first_name,other_names', 'pregnancy:id', 'births'])
                ->latest('delivered_at')
                ->limit(8)
                ->get()
                ->map(fn (Delivery $d) => [
                    'id' => $d->id,
                    'mother' => $d->patient->fullName(),
                    'file_number' => $d->patient->file_number,
                    'delivered_at' => $d->delivered_at->isoFormat('D MMM, HH:mm'),
                    'mode' => $d->mode->label(),
                    'babies' => $d->births->count(),
                    'live' => $d->births->filter(fn (Birth $b) => $b->outcome->isLive())->count(),
                    'maternal_outcome' => $d->maternal_outcome->label(),
                    'url' => route('maternity.show', $d->pregnancy_id),
                ]),
            'riskFactors' => Pregnancy::RISK_FACTORS,
            'preselected' => $preselected ? $this->patientCard($preselected) : null,
        ]);
    }

    /**
     * Find a woman to book.
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());

        $patients = Patient::query()
            ->where('sex', 'F')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('other_names', 'like', "%{$q}%")))
            ->orderBy('surname')
            ->limit(10)
            ->get()
            ->map(fn (Patient $p) => [
                ...$this->patientCard($p),
                'has_active_pregnancy' => $p->activePregnancy() !== null,
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Book a pregnancy.
     */
    public function store(StorePregnancyRequest $request): RedirectResponse
    {
        $patient = Patient::findOrFail($request->integer('patient_id'));

        $pregnancy = $this->maternity->book($patient, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->fullName()} booked for antenatal care."]);

        return to_route('maternity.show', $pregnancy);
    }

    /**
     * The pregnancy record.
     */
    public function show(Pregnancy $pregnancy): Response
    {
        $pregnancy->load([
            'patient',
            'bookedBy:id,name',
            'closedBy:id,name',
            'delivery.attendant:id,name',
            'delivery.recordedBy:id,name',
            'delivery.births.newborn:id,file_number',
        ]);

        $ancVisits = $pregnancy->ancVisitsSinceBooking()
            ->with(['author:id,name', 'observationSets.observations'])
            ->get();
        $admission = $pregnancy->patient->activeAdmission();

        return Inertia::render('maternity/Show', [
            'pregnancy' => [
                'id' => $pregnancy->id,
                'pregnancy_number' => $pregnancy->pregnancy_number,
                'status' => $pregnancy->status->value,
                'status_label' => $pregnancy->status->label(),
                'tone' => $pregnancy->status->tone(),
                'is_active' => $pregnancy->isActive(),
                'lmp' => $pregnancy->lmp?->toDateString(),
                'lmp_label' => $pregnancy->lmp?->isoFormat('D MMM YYYY'),
                'edd' => $pregnancy->edd?->toDateString(),
                'edd_label' => $pregnancy->edd?->isoFormat('D MMM YYYY'),
                'edd_diff' => $pregnancy->edd?->diffForHumans(),
                'ga_weeks' => $pregnancy->gestationalAgeWeeks(),
                'overdue' => $pregnancy->isOverdue(),
                'gravida' => $pregnancy->gravida,
                'para' => $pregnancy->para,
                'booking_date' => $pregnancy->booking_date?->toDateString(),
                'booking_date_label' => $pregnancy->booking_date?->isoFormat('D MMM YYYY'),
                'booked_by' => $pregnancy->bookedBy?->name,
                'risk_factors' => $pregnancy->risk_factors ?? [],
                'notes' => $pregnancy->notes,
                'outcome_note' => $pregnancy->outcome_note,
                'closed_at' => $pregnancy->closed_at?->isoFormat('D MMM YYYY, h:mm a'),
                'closed_by' => $pregnancy->closedBy?->name,
            ],
            'patient' => [
                ...$this->patientCard($pregnancy->patient),
                'phone' => $pregnancy->patient->phone,
                'coverage_label' => $pregnancy->patient->coverage === 'hmo'
                    ? ($pregnancy->patient->hmo_name ?: 'HMO')
                    : 'Private',
            ],
            'admission' => $admission ? [
                'ward' => $admission->ward?->name,
                'bed' => $admission->bed?->label,
                'status_label' => $admission->status->label(),
                'url' => route('admissions.show', $admission),
            ] : null,
            'ancVisits' => $ancVisits->map(function (Encounter $e) {
                // Antenatal findings are observations; the latest set of the
                // encounter carries the examination.
                $readings = $e->observationSets->first()?->values() ?? [];

                return [
                    'id' => $e->id,
                    'date' => $e->signed_at?->isoFormat('D MMM YYYY'),
                    'ga_weeks' => $readings[ObservationCode::GestationalAge->value] ?? null,
                    'fundal_height_cm' => $readings[ObservationCode::FundalHeight->value] ?? null,
                    'fetal_heart_rate' => $readings[ObservationCode::FetalHeartRate->value] ?? null,
                    'presentation' => $readings[ObservationCode::Presentation->value] ?? null,
                    'assessment' => $e->assessment,
                    'by' => $e->author?->name,
                    'url' => route('encounters.show', $e),
                ];
            }),
            'delivery' => $pregnancy->delivery ? $this->deliveryCard($pregnancy->delivery) : null,
            'options' => [
                'riskFactors' => Pregnancy::RISK_FACTORS,
                'modes' => DeliveryMode::options(),
                'labourOnsets' => collect(Delivery::LABOUR_ONSETS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'places' => collect(Delivery::PLACES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'complications' => Delivery::COMPLICATIONS,
                'maternalOutcomes' => MaternalOutcome::options(),
                'birthOutcomes' => BirthOutcome::options(),
                'conditions' => collect(Birth::CONDITIONS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'attendants' => $this->attendants(),
            ],
        ]);
    }

    /**
     * Revise booking details.
     */
    public function update(StorePregnancyRequest $request, Pregnancy $pregnancy): RedirectResponse
    {
        $this->maternity->update($pregnancy, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pregnancy details saved.']);

        return back();
    }

    /**
     * Close a pregnancy that ended without a delivery.
     */
    public function close(Request $request, Pregnancy $pregnancy): RedirectResponse
    {
        $data = $request->validate(['outcome_note' => ['required', 'string', 'max:500']]);

        $this->maternity->closeAsLoss($pregnancy, $request->user(), $data['outcome_note']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pregnancy closed.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function deliveryCard(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivered_at' => $delivery->delivered_at->isoFormat('D MMM YYYY, HH:mm'),
            'mode' => $delivery->mode->value,
            'mode_label' => $delivery->mode->label(),
            'is_caesarean' => $delivery->mode->isCaesarean(),
            'labour_onset' => Delivery::LABOUR_ONSETS[$delivery->labour_onset] ?? null,
            'gestational_age_weeks' => $delivery->gestational_age_weeks,
            'place' => Delivery::PLACES[$delivery->place] ?? $delivery->place,
            'attendant' => $delivery->attendant?->name,
            'complications' => $delivery->complications ?? [],
            'blood_loss_ml' => $delivery->blood_loss_ml,
            'maternal_outcome' => $delivery->maternal_outcome->value,
            'maternal_outcome_label' => $delivery->maternal_outcome->label(),
            'notes' => $delivery->notes,
            'recorded_by' => $delivery->recordedBy?->name,
            'admission_url' => $delivery->admission_id ? route('admissions.show', $delivery->admission_id) : null,
            'births' => $delivery->births->map(fn (Birth $b) => [
                'id' => $b->id,
                'birth_order' => $b->birth_order,
                'outcome' => $b->outcome->value,
                'outcome_label' => $b->outcome->label(),
                'is_live' => $b->outcome->isLive(),
                'sex' => $b->sex,
                'weight_grams' => $b->weight_grams,
                'low_birth_weight' => $b->isLowBirthWeight(),
                'apgar_1' => $b->apgar_1,
                'apgar_5' => $b->apgar_5,
                'resuscitated' => $b->resuscitated,
                'breastfed_within_hour' => $b->breastfed_within_hour,
                'bcg_given' => $b->bcg_given,
                'opv0_given' => $b->opv0_given,
                'hepb0_given' => $b->hepb0_given,
                'condition' => Birth::CONDITIONS[$b->condition] ?? $b->condition,
                'notes' => $b->notes,
                'newborn' => $b->newborn ? [
                    'file_number' => $b->newborn->file_number,
                    'url' => route('patients.show', $b->newborn),
                ] : null,
            ]),
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
     * Staff who can be named as the delivery attendant.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function attendants(): Collection
    {
        return User::query()
            ->whereNull('deactivated_at')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['physician', 'nurse', 'chief-medical-director']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]);
    }
}

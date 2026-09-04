<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralStatusRequest;
use App\Http\Resources\ReferralResource;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outbound referrals: issued from an encounter, tracked in a register,
 * printed as a letter.
 */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals) {}

    /**
     * Issue a referral from an encounter.
     */
    public function store(StoreReferralRequest $request, Encounter $encounter): RedirectResponse
    {
        $referral = $this->referrals->create($encounter, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Referral {$referral->referral_number} issued. Print the letter for the patient to take along."]);

        return back();
    }

    /**
     * The referral register, open referrals first.
     */
    public function index(Request $request): Response
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $filters = [
            'status' => (string) $request->string('status'),
            'urgency' => (string) $request->string('urgency'),
            'search' => trim((string) $request->string('search')),
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
        ];

        $referrals = Referral::query()
            ->with(['patient:id,file_number,surname,first_name,other_names,sex,date_of_birth', 'referredBy:id,name'])
            ->when($filters['status'] === 'open', fn (Builder $q) => $q->open())
            ->when($filters['status'] !== '' && $filters['status'] !== 'open', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['urgency'] !== '', fn (Builder $q) => $q->where('urgency', $filters['urgency']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $term = "%{$filters['search']}%";

                $query->where(fn (Builder $q) => $q
                    ->where('referral_number', 'like', $term)
                    ->orWhere('destination_facility', 'like', $term)
                    ->orWhereIn('patient_id', Patient::query()
                        ->select('id')
                        ->where(fn (Builder $p) => $p
                            ->where('file_number', 'like', $term)
                            ->orWhere('surname', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('other_names', 'like', $term))));
            })
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('referred_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('referred_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->orderByRaw("case when status in ('issued', 'accepted') then 0 else 1 end")
            ->orderByDesc('referred_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Referral $referral) => $this->row($referral));

        return Inertia::render('referrals/Index', [
            'referrals' => $referrals,
            'filters' => $filters,
            'summary' => [
                'open' => Referral::query()->open()->count(),
                'awaiting_feedback' => Referral::query()->open()->where('referred_at', '<=', now()->subDays(14))->count(),
                'this_month' => Referral::query()->where('referred_at', '>=', Carbon::today()->startOfMonth())->count(),
            ],
            'statuses' => ReferralStatus::options(),
            'urgencies' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => $p->label()])->values(),
        ]);
    }

    /**
     * One referral: the letter content, its status and the feedback.
     */
    public function show(Referral $referral): Response
    {
        $referral->load(['patient', 'encounter:id,type', 'referredBy:id,name', 'closedBy:id,name']);

        return Inertia::render('referrals/Show', [
            'referral' => [
                ...ReferralResource::make($referral)->resolve(),
                'patient' => [
                    'id' => $referral->patient->id,
                    'name' => $referral->patient->fullName(),
                    'file_number' => $referral->patient->file_number,
                    'sex' => $referral->patient->sex,
                    'age' => $referral->patient->age(),
                    'phone' => $referral->patient->phone,
                    'url' => route('patients.show', $referral->patient),
                ],
                'encounter_url' => $referral->encounter ? route('encounters.show', $referral->encounter) : null,
                'transitions' => array_map(
                    fn (ReferralStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    $referral->status->transitions(),
                ),
            ],
        ]);
    }

    /**
     * Record what happened at the receiving facility.
     */
    public function status(UpdateReferralStatusRequest $request, Referral $referral): RedirectResponse
    {
        $data = $request->validated();

        $this->referrals->setStatus($referral, ReferralStatus::from($data['status']), $data['feedback'] ?? null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Referral marked {$referral->status->label()}."]);

        return back();
    }

    /**
     * The referral letter as a PDF for the patient to take along.
     */
    public function letter(Referral $referral): HttpResponse
    {
        $pdf = $this->referrals->letter($referral);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('/', '-', $referral->referral_number).'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Referral $referral): array
    {
        $patient = $referral->patient;

        return [
            ...ReferralResource::make($referral)->resolve(),
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
                'sex' => $patient->sex,
                'age' => $patient->age(),
            ],
            'days_open' => $referral->status->isOpen() ? (int) $referral->referred_at->diffInDays(now()) : null,
        ];
    }
}

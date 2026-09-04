<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Http\Requests\ClaimAuthorizationRequest;
use App\Http\Requests\RemitClaimRequest;
use App\Http\Requests\StoreClaimRequest;
use App\Http\Requests\UpdateClaimLineRequest;
use App\Models\Bill;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Models\Patient;
use App\Models\Payer;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The claims desk: raising claims from bills, preparing them, and following
 * them through to remittance.
 */
class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claims) {}

    /**
     * The claims register with headline figures.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->value();
        $payerId = $request->integer('payer_id');
        $q = trim($request->string('q')->value());

        $claims = Claim::query()
            ->with(['patient:id,file_number,surname,first_name,other_names', 'payer:id,name,code', 'batch:id,batch_number'])
            ->when($status === 'outstanding', fn ($query) => $query->outstanding())
            ->when($status !== '' && $status !== 'outstanding', fn ($query) => $query->where('status', $status))
            ->when($payerId, fn ($query) => $query->where('payer_id', $payerId))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('claim_number', 'like', "%{$q}%")
                ->orWhere('enrollee_number', 'like', "%{$q}%")
                ->orWhereHas('patient', fn ($p) => $p
                    ->where('file_number', 'like', "%{$q}%")
                    ->orWhere('surname', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%"))))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Claim $claim) => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'patient' => $claim->patient->fullName(),
                'file_number' => $claim->patient->file_number,
                'payer' => $claim->payer->name,
                'payer_code' => $claim->payer->code,
                'service_date' => $claim->service_date->isoFormat('D MMM YYYY'),
                'gross_amount' => $claim->gross_amount,
                'payer_amount' => $claim->payer_amount,
                'paid_amount' => $claim->paid_amount,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'tone' => $claim->status->tone(),
                'batch' => $claim->batch?->batch_number,
                'has_authorization' => $claim->authorization_code !== null,
                'url' => route('claims.show', $claim),
            ]);

        $monthStart = now()->startOfMonth();

        // Arriving from a bill pre-selects it in the new-claim dialog.
        $preselectedBill = $request->filled('bill_id') ? Bill::find($request->integer('bill_id')) : null;

        return Inertia::render('claims/Index', [
            'claims' => $claims,
            'filters' => ['status' => $status, 'payer_id' => $payerId ?: null, 'q' => $q],
            'stats' => [
                'draft_count' => Claim::where('status', ClaimStatus::Draft->value)->count(),
                'draft_amount' => (float) Claim::where('status', ClaimStatus::Draft->value)->sum('payer_amount'),
                'outstanding_count' => Claim::query()->outstanding()->count(),
                'outstanding_amount' => (float) Claim::query()->outstanding()->get()->sum(fn (Claim $c) => $c->outstandingAmount()),
                'paid_month' => (float) Claim::where('remitted_at', '>=', $monthStart)->sum('paid_amount'),
                'rejected_count' => Claim::where('status', ClaimStatus::Rejected->value)->where('updated_at', '>=', now()->subDays(90))->count(),
            ],
            'payers' => Payer::query()->orderBy('name')->get(['id', 'name', 'code', 'is_active'])
                ->map(fn (Payer $p) => ['id' => $p->id, 'name' => $p->name, 'code' => $p->code, 'is_active' => $p->is_active]),
            'statuses' => ClaimStatus::options(),
            'preselectedBill' => $preselectedBill ? $this->claimableBill($preselectedBill) : null,
        ]);
    }

    /**
     * Find an HMO patient's bills with charges not yet claimed.
     */
    public function billSearch(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->value());

        $patients = Patient::query()
            ->where('coverage', 'hmo')
            ->with('payer:id,name,is_active')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$q}%")
                ->orWhere('hmo_number', 'like', "%{$q}%")
                ->orWhere('surname', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('other_names', 'like', "%{$q}%")))
            ->orderBy('surname')
            ->limit(8)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'name' => $p->fullName(),
                'file_number' => $p->file_number,
                'enrollee_number' => $p->hmo_number,
                'payer' => $p->payer?->name ?? $p->hmo_name,
                'payer_active' => (bool) $p->payer?->is_active,
                'bills' => Bill::query()
                    ->where('patient_id', $p->id)
                    ->whereHas('charges', fn ($c) => $c->whereDoesntHave('claimLine'))
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (Bill $bill) => $this->claimableBill($bill))
                    ->values(),
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Raise a claim from a bill.
     */
    public function store(StoreClaimRequest $request): RedirectResponse
    {
        $bill = Bill::findOrFail($request->integer('bill_id'));
        $chargeIds = $request->filled('charge_ids') ? array_map('intval', $request->input('charge_ids')) : null;

        $claim = $this->claims->createFromBill($bill, $request->user(), $chargeIds);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Claim {$claim->claim_number} raised."]);

        return to_route('claims.show', $claim);
    }

    /**
     * The claim itself.
     */
    public function show(Request $request, Claim $claim): Response
    {
        $claim->load([
            'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth,phone,hmo_number,hmo_plan,hmo_expires_at',
            'payer',
            'bill',
            'batch:id,batch_number,status',
            'lines',
            'createdBy:id,name',
            'submittedBy:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('claims/Show', [
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'tone' => $claim->status->tone(),
                'is_draft' => $claim->status === ClaimStatus::Draft,
                'is_outstanding' => $claim->status->isOutstanding(),
                'is_open' => $claim->status->isOpen(),
                'enrollee_number' => $claim->enrollee_number,
                'plan' => $claim->plan,
                'service_date' => $claim->service_date->isoFormat('D MMM YYYY'),
                'diagnosis' => $claim->diagnosis,
                'authorization_code' => $claim->authorization_code,
                'authorized_at' => $claim->authorized_at?->isoFormat('D MMM YYYY'),
                'authorization_note' => $claim->authorization_note,
                'gross_amount' => $claim->gross_amount,
                'discount_amount' => $claim->discount_amount,
                'copay_amount' => $claim->copay_amount,
                'payer_amount' => $claim->payer_amount,
                'approved_amount' => $claim->approved_amount,
                'paid_amount' => $claim->paid_amount,
                'outstanding_amount' => $claim->outstandingAmount(),
                'shortfall_amount' => $claim->shortfallAmount(),
                'rejection_reason' => $claim->rejection_reason,
                'remitted_at' => $claim->remitted_at?->isoFormat('D MMM YYYY'),
                'remittance_reference' => $claim->remittance_reference,
                'notes' => $claim->notes,
                'created_by' => $claim->createdBy?->name,
                'created_at' => $claim->created_at?->isoFormat('D MMM YYYY, h:mm a'),
                'submitted_by' => $claim->submittedBy?->name,
                'submitted_at' => $claim->submitted_at?->isoFormat('D MMM YYYY, h:mm a'),
                'batch' => $claim->batch ? [
                    'batch_number' => $claim->batch->batch_number,
                    'url' => route('claims.batches.show', $claim->batch),
                ] : null,
                'bill_url' => $user->canAccessModule('billing') ? route('billing.show', $claim->bill) : null,
                'bill_balance' => $claim->bill->balance(),
            ],
            'patient' => [
                'id' => $claim->patient->id,
                'name' => $claim->patient->fullName(),
                'initials' => $claim->patient->initials(),
                'file_number' => $claim->patient->file_number,
                'sex' => $claim->patient->sex,
                'age' => $claim->patient->age(),
                'phone' => $claim->patient->phone,
                'hmo_expires_at' => $claim->patient->hmo_expires_at?->isoFormat('D MMM YYYY'),
                'hmo_expired' => $claim->patient->hmo_expires_at?->isPast() ?? false,
                'url' => route('patients.show', $claim->patient),
            ],
            'payer' => [
                'id' => $claim->payer->id,
                'name' => $claim->payer->name,
                'code' => $claim->payer->code,
                'type_label' => $claim->payer->type->label(),
                'discount_percent' => $claim->payer->discount_percent,
                'drug_copay_percent' => $claim->payer->drug_copay_percent,
            ],
            'lines' => $claim->lines->map(fn (ClaimLine $line) => [
                'id' => $line->id,
                'source' => $line->source,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'gross_amount' => $line->gross_amount,
                'amount' => $line->amount,
                'copay_amount' => $line->copay_amount,
                'payer_amount' => $line->payer_amount,
                'is_covered' => $line->is_covered,
                'remark' => $line->remark,
            ]),
        ]);
    }

    /**
     * Revise a line on a draft claim.
     */
    public function updateLine(UpdateClaimLineRequest $request, Claim $claim, ClaimLine $line): RedirectResponse
    {
        abort_unless($line->claim_id === $claim->id, 404);

        $data = $request->validated();

        $this->claims->updateLine($line, [
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'copay_amount' => isset($data['copay_amount']) ? (float) $data['copay_amount'] : null,
            'is_covered' => array_key_exists('is_covered', $data) ? (bool) $data['is_covered'] : null,
            ...(array_key_exists('remark', $data) ? ['remark' => $data['remark']] : []),
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Line updated.']);

        return back();
    }

    /**
     * Record the payer's pre-authorisation.
     */
    public function authorize(ClaimAuthorizationRequest $request, Claim $claim): RedirectResponse
    {
        $this->claims->setAuthorization(
            $claim,
            $request->string('authorization_code')->value(),
            $request->filled('authorized_at') ? Carbon::parse($request->input('authorized_at')) : null,
            $request->input('authorization_note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Authorisation recorded.']);

        return back();
    }

    /**
     * Submit the claim into this month's schedule for the payer.
     */
    public function submit(Request $request, Claim $claim): RedirectResponse
    {
        $claim = $this->claims->submit($claim, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Submitted in schedule {$claim->batch?->batch_number}."]);

        return back();
    }

    /**
     * Record a remittance from the payer.
     */
    public function remit(RemitClaimRequest $request, Claim $claim): RedirectResponse
    {
        $this->claims->recordRemittance(
            claim: $claim,
            approved: (float) $request->input('approved_amount'),
            paid: (float) $request->input('paid_amount'),
            reference: $request->input('reference'),
            paidAt: $request->filled('paid_at') ? Carbon::parse($request->input('paid_at')) : null,
            actor: $request->user(),
            note: $request->input('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Remittance recorded.']);

        return back();
    }

    /**
     * Record that the payer rejected the claim.
     */
    public function reject(Request $request, Claim $claim): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->claims->reject($claim, $data['reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Claim marked as rejected.']);

        return back();
    }

    /**
     * Discard a draft claim.
     */
    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        $this->claims->void($claim, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft claim discarded.']);

        return to_route('claims.index');
    }

    /**
     * A bill as offered in the new-claim dialog.
     *
     * @return array<string, mixed>
     */
    private function claimableBill(Bill $bill): array
    {
        $bill->loadMissing('patient.payer');

        $unclaimed = $bill->charges()->whereDoesntHave('claimLine')->get();

        return [
            'id' => $bill->id,
            'label' => 'Bill #'.$bill->id,
            'opened_at' => $bill->created_at?->isoFormat('D MMM YYYY'),
            'total' => $bill->total(),
            'unclaimed_count' => $unclaimed->count(),
            'unclaimed_total' => round((float) $unclaimed->sum('total'), 2),
            'patient' => [
                'id' => $bill->patient->id,
                'name' => $bill->patient->fullName(),
                'file_number' => $bill->patient->file_number,
                'enrollee_number' => $bill->patient->hmo_number,
                'payer' => $bill->patient->payer?->name ?? $bill->patient->hmo_name,
                'payer_active' => (bool) $bill->patient->payer?->is_active,
            ],
        ];
    }
}

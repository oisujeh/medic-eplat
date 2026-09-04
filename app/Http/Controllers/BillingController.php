<?php

namespace App\Http\Controllers;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillCharge;
use App\Models\Payment;
use App\Models\ServiceCharge;
use App\Services\BillingService;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Bills across the facility, newest first.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status', 'open')->toString();

        $bills = Bill::query()
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($search !== '', fn ($q) => $q->whereHas('patient', fn ($p) => $p
                ->where('file_number', 'like', "%{$search}%")
                ->orWhere('surname', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")))
            ->latest()
            ->with('patient:id,file_number,surname,first_name,other_names,sex,date_of_birth')
            ->withSum('charges as total_amount', 'total')
            ->withCount('charges')
            ->take(60)
            ->get()
            ->map(fn (Bill $bill) => $this->billCard($bill));

        return Inertia::render('billing/Index', [
            'bills' => $bills,
            'filters' => ['q' => $search, 'status' => $status],
            'counts' => [
                'open' => Bill::query()->open()->count(),
                'paid' => Bill::where('status', 'paid')->count(),
            ],
        ]);
    }

    /**
     * A single bill with its itemised charges.
     */
    public function show(Request $request, Bill $bill): Response
    {
        $bill->load(['patient.payer', 'claims', 'charges' => fn ($q) => $q->with('claimLine:id,bill_charge_id,claim_id')->latest(), 'payments' => fn ($q) => $q->with('receivedBy:id,name')->latest()]);

        $canClaim = $request->user()->canAccessModule('claims')
            && $bill->patient->coverage === 'hmo'
            && $bill->charges->contains(fn (BillCharge $c) => $c->claimLine === null);

        return Inertia::render('billing/Bill', [
            'canClaim' => $canClaim,
            'claims' => $bill->claims->map(fn ($claim) => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status_label' => $claim->status->label(),
                'tone' => $claim->status->tone(),
                'url' => $request->user()->canAccessModule('claims') ? route('claims.show', $claim) : null,
            ]),
            'bill' => [
                'id' => $bill->id,
                'status' => $bill->status->value,
                'status_label' => $bill->status->label(),
                'tone' => $bill->status->tone(),
                'is_open' => $bill->status->isOpen(),
                'total' => $bill->total(),
                'paid' => $bill->paidTotal(),
                'balance' => $bill->balance(),
                'created_at' => $bill->created_at?->isoFormat('D MMM YYYY'),
            ],
            'payments' => $bill->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'received_by' => $p->receivedBy?->name,
                'at' => $p->created_at?->isoFormat('D MMM, h:mm a'),
            ]),
            'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
            'services' => ServiceCharge::query()->active()->get()->map(fn (ServiceCharge $s) => [
                'id' => $s->id,
                'label' => $s->label(),
                'price' => $s->price,
                'category' => $s->category->value,
            ]),
            'patient' => [
                'id' => $bill->patient->id,
                'name' => $bill->patient->fullName(),
                'initials' => $bill->patient->initials(),
                'file_number' => $bill->patient->file_number,
                'sex_label' => $bill->patient->sex === 'F' ? 'Female' : 'Male',
                'age' => $bill->patient->age(),
                'coverage' => $bill->patient->coverage,
                'payer' => $bill->patient->payer?->name ?? $bill->patient->hmo_name,
                'url' => route('patients.show', $bill->patient->id),
            ],
            'charges' => $bill->charges->map(fn (BillCharge $c) => [
                'id' => $c->id,
                'source' => $c->source,
                'description' => $c->description,
                'quantity' => $c->quantity,
                'unit_price' => $c->unit_price,
                'total' => $c->total,
                'claimed' => $c->claimLine !== null,
                'at' => $c->created_at?->isoFormat('D MMM, h:mm a'),
            ]),
        ]);
    }

    /**
     * Add a charge to a bill — from the fee schedule or a custom line.
     */
    public function addCharge(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($bill->status->isOpen(), 422);

        $data = $request->validate([
            'service_charge_id' => ['nullable', Rule::exists('service_charges', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:255', 'required_without:service_charge_id'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'required_without:service_charge_id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! empty($data['service_charge_id'])) {
            $service = ServiceCharge::findOrFail((int) $data['service_charge_id']);
            $description = $service->label();
            $unitPrice = $service->price;
            $source = $service->category->value;
        } else {
            $description = $data['description'];
            $unitPrice = (float) $data['unit_price'];
            $source = 'other';
        }

        $this->billing->postCharge($bill, $source, $description, (int) $data['quantity'], $unitPrice, $request->user());
        $this->billing->refreshStatus($bill);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Charge added.']);

        return back();
    }

    /**
     * Record a payment against a bill.
     */
    public function pay(Request $request, Bill $bill): RedirectResponse
    {
        abort_unless($bill->status->isOpen(), 422);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->billing->recordPayment(
            $bill,
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            $data['reference'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment recorded.']);

        return back();
    }

    /**
     * Render the bill as a downloadable PDF invoice.
     */
    public function invoice(Bill $bill): HttpResponse
    {
        $bill->load([
            'patient',
            'charges' => fn ($q) => $q->oldest(),
            'payments' => fn ($q) => $q->oldest(),
        ]);

        $patient = $bill->patient;
        $money = fn (float $v): string => '₦'.number_format($v, 2);

        $html = view('invoices.bill', [
            'facility' => [
                'name' => config('app.name'),
                'address' => 'Plot 4, Health Avenue, Central District',
                'contact' => 'accounts@medic-eplat.test  ·  +234 800 000 0000',
            ],
            'invoice' => [
                'number' => sprintf('INV-%06d', $bill->id),
                'date' => ($bill->created_at ?? now())->isoFormat('D MMM YYYY'),
                'status_label' => $bill->status->label(),
                'badge_class' => match ($bill->status) {
                    BillStatus::Open => 'badge-open',
                    BillStatus::PartiallyPaid => 'badge-part',
                    BillStatus::Paid => 'badge-paid',
                    BillStatus::Cancelled => 'badge-void',
                },
                'generated_at' => now()->isoFormat('D MMM YYYY, h:mm a'),
            ],
            'patient' => [
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
                'detail' => trim(($patient->sex === 'F' ? 'Female' : 'Male')
                    .($patient->age() !== null ? ' · '.$patient->age().'y' : '')),
            ],
            'charges' => $bill->charges->map(fn (BillCharge $c) => [
                'description' => $c->description,
                'source' => $c->source,
                'quantity' => $c->quantity,
                'unit_price' => $c->unit_price,
                'total' => $c->total,
                'at' => $c->created_at?->isoFormat('D MMM, h:mm a'),
            ])->all(),
            'payments' => $bill->payments->map(fn (Payment $p) => [
                'amount' => $p->amount,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'at' => $p->created_at?->isoFormat('D MMM YYYY, h:mm a'),
            ])->all(),
            'totals' => [
                'total' => $bill->total(),
                'paid' => $bill->paidTotal(),
                'balance' => $bill->balance(),
            ],
            'money' => $money,
        ])->render();

        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.sprintf('invoice-%06d.pdf', $bill->id).'"',
        ]);
    }

    /**
     * Compact representation of a bill for the list.
     *
     * @return array<string, mixed>
     */
    private function billCard(Bill $bill): array
    {
        return [
            'id' => $bill->id,
            'status' => $bill->status->value,
            'status_label' => $bill->status->label(),
            'tone' => $bill->status->tone(),
            'total' => (float) ($bill->total_amount ?? 0),
            'charges_count' => $bill->charges_count,
            'created_at' => $bill->created_at?->diffForHumans(short: true),
            'patient' => [
                'name' => $bill->patient->fullName(),
                'initials' => $bill->patient->initials(),
                'file_number' => $bill->patient->file_number,
            ],
            'url' => route('billing.show', $bill),
        ];
    }
}

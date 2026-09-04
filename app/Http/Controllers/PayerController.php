<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Enums\PayerType;
use App\Http\Requests\StorePayerRequest;
use App\Http\Requests\UpdatePayerRequest;
use App\Models\Payer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payers a facility holds contracts with, and their tariff rules.
 */
class PayerController extends Controller
{
    /**
     * Every payer with its enrolment and claims footprint.
     */
    public function index(): Response
    {
        $payers = Payer::query()
            ->withCount('patients')
            ->withSum(['claims as outstanding_amount' => fn ($q) => $q->whereIn('status', [
                ClaimStatus::Submitted->value, ClaimStatus::PartiallyPaid->value,
            ])], 'payer_amount')
            ->withCount(['claims as draft_claims_count' => fn ($q) => $q->where('status', ClaimStatus::Draft->value)])
            ->orderByDesc('is_active')
            ->orderByRaw('lower(name)')
            ->get();

        return Inertia::render('claims/Payers', [
            'payers' => $payers->map(fn (Payer $payer) => [
                'id' => $payer->id,
                'name' => $payer->name,
                'code' => $payer->code,
                'type' => $payer->type->value,
                'type_label' => $payer->type->label(),
                'discount_percent' => $payer->discount_percent,
                'drug_copay_percent' => $payer->drug_copay_percent,
                'contact_person' => $payer->contact_person,
                'phone' => $payer->phone,
                'email' => $payer->email,
                'address' => $payer->address,
                'notes' => $payer->notes,
                'is_active' => $payer->is_active,
                'patients_count' => $payer->patients_count,
                'draft_claims_count' => $payer->draft_claims_count,
                'outstanding_amount' => (float) ($payer->outstanding_amount ?? 0),
            ]),
            'payerTypes' => PayerType::options(),
        ]);
    }

    /**
     * Register a payer.
     */
    public function store(StorePayerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $payer = Payer::create([
            ...$data,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'drug_copay_percent' => $data['drug_copay_percent'] ?? 0,
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$payer->name} added."]);

        return back();
    }

    /**
     * Update a payer's details and tariff rules.
     */
    public function update(UpdatePayerRequest $request, Payer $payer): RedirectResponse
    {
        $data = $request->validated();

        $payer->update([
            ...$data,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'drug_copay_percent' => $data['drug_copay_percent'] ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$payer->name} saved."]);

        return back();
    }
}

<?php

namespace App\Services;

use App\Enums\ClaimBatchStatus;
use App\Enums\ClaimStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillCharge;
use App\Models\Claim;
use App\Models\ClaimBatch;
use App\Models\ClaimLine;
use App\Models\Encounter;
use App\Models\Payer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Building claims from bills, keeping the bill settled for the payer's share,
 * and carrying each claim through submission and remittance.
 */
class ClaimService
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Raise a draft claim for the charges on a bill that are not yet claimed.
     * The payer's share and any tariff discount are recorded on the bill so
     * the cashier only collects the enrollee's co-payment.
     *
     * @param  array<int, int>|null  $chargeIds  restrict to these charges
     *
     * @throws ValidationException when the bill cannot be claimed
     */
    public function createFromBill(Bill $bill, User $actor, ?array $chargeIds = null): Claim
    {
        $patient = $bill->patient;
        $payer = $patient->payer;

        if ($patient->coverage !== 'hmo' || ! $payer) {
            throw ValidationException::withMessages([
                'bill_id' => "{$patient->fullName()} has no HMO or scheme on record. Update their coverage first.",
            ]);
        }

        if (! $payer->is_active) {
            throw ValidationException::withMessages(['bill_id' => "{$payer->name} is not accepting claims."]);
        }

        $charges = $bill->charges()
            ->whereDoesntHave('claimLine')
            ->when($chargeIds, fn ($q) => $q->whereIn('id', $chargeIds))
            ->oldest('id')
            ->get();

        if ($charges->isEmpty()) {
            throw ValidationException::withMessages(['bill_id' => 'Every charge on this bill is already on a claim.']);
        }

        return DB::transaction(function () use ($bill, $patient, $payer, $charges, $actor) {
            $claim = Claim::create([
                'claim_number' => 'TMP-'.$bill->id.'-'.now()->timestamp,
                'patient_id' => $patient->id,
                'payer_id' => $payer->id,
                'bill_id' => $bill->id,
                'visit_id' => $bill->visit_id,
                'status' => ClaimStatus::Draft,
                'enrollee_number' => $patient->hmo_number,
                'plan' => $patient->hmo_plan,
                'service_date' => ($bill->created_at ?? now())->toDateString(),
                'diagnosis' => $this->diagnosisFor($bill),
                'created_by' => $actor->id,
            ]);

            $claim->update(['claim_number' => sprintf('CLM/%d/%06d', now()->year, $claim->id)]);

            foreach ($charges as $charge) {
                $claim->lines()->create($this->lineFor($charge, $payer));
            }

            $this->recalculate($claim, $actor);

            return $claim->refresh();
        });
    }

    /**
     * Revise a line while the claim is still a draft.
     *
     * @param  array{amount?: float|null, copay_amount?: float|null, is_covered?: bool|null, remark?: string|null}  $data
     */
    public function updateLine(ClaimLine $line, array $data, User $actor): ClaimLine
    {
        $claim = $line->claim;
        $this->assertStatus($claim, [ClaimStatus::Draft], 'Lines can only be changed on a draft claim.');

        $covered = $data['is_covered'] ?? $line->is_covered;

        if (! $covered) {
            // The enrollee pays the facility price for anything the payer
            // does not cover.
            $amount = $line->gross_amount;
            $copay = $line->gross_amount;
        } else {
            $amount = round((float) ($data['amount'] ?? $line->amount), 2);
            $copay = round((float) ($data['copay_amount'] ?? $line->copay_amount), 2);

            if ($copay > $amount) {
                throw ValidationException::withMessages(['copay_amount' => 'The co-payment cannot exceed the claimed amount.']);
            }
        }

        $line->update([
            'is_covered' => $covered,
            'amount' => $amount,
            'copay_amount' => $copay,
            'payer_amount' => round($amount - $copay, 2),
            'remark' => array_key_exists('remark', $data) ? $data['remark'] : $line->remark,
        ]);

        $this->recalculate($claim, $actor);

        return $line->refresh();
    }

    /**
     * Record the payer's pre-authorisation for the claim.
     */
    public function setAuthorization(Claim $claim, string $code, ?Carbon $authorizedAt, ?string $note): Claim
    {
        $this->assertStatus($claim, [ClaimStatus::Draft, ClaimStatus::Submitted, ClaimStatus::PartiallyPaid], 'This claim is closed.');

        $claim->update([
            'authorization_code' => $code,
            'authorized_at' => $authorizedAt ?? now(),
            'authorization_note' => $note,
        ]);

        return $claim;
    }

    /**
     * Submit a draft claim into the payer's open schedule for this month.
     */
    public function submit(Claim $claim, User $actor): Claim
    {
        $this->assertStatus($claim, [ClaimStatus::Draft], 'Only a draft claim can be submitted.');

        if ($claim->lines()->where('is_covered', true)->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'Nothing on this claim is covered by the payer.']);
        }

        return DB::transaction(function () use ($claim, $actor) {
            $batch = $this->openBatchFor($claim->payer, now()->format('Y-m'));

            $claim->update([
                'status' => ClaimStatus::Submitted,
                'claim_batch_id' => $batch->id,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            return $claim->refresh();
        });
    }

    /**
     * Record money received from the payer. The claim is settled once the
     * approved amount has been paid in full.
     */
    public function recordRemittance(
        Claim $claim,
        float $approved,
        float $paid,
        ?string $reference,
        ?Carbon $paidAt,
        User $actor,
        ?string $note = null,
    ): Claim {
        $this->assertStatus($claim, [ClaimStatus::Submitted, ClaimStatus::PartiallyPaid], 'Only a submitted claim can be remitted.');

        $paidTotal = round($claim->paid_amount + $paid, 2);

        $claim->update([
            'approved_amount' => round($approved, 2),
            'paid_amount' => $paidTotal,
            'remitted_at' => $paidAt ?? now(),
            'remittance_reference' => $reference ?: $claim->remittance_reference,
            'notes' => $note ? trim(($claim->notes ? $claim->notes."\n" : '').$note) : $claim->notes,
            'status' => $paidTotal >= round($approved, 2) ? ClaimStatus::Paid : ClaimStatus::PartiallyPaid,
        ]);

        return $claim;
    }

    /**
     * Record that the payer declined the claim.
     */
    public function reject(Claim $claim, string $reason, User $actor): Claim
    {
        $this->assertStatus($claim, [ClaimStatus::Submitted, ClaimStatus::PartiallyPaid], 'Only a submitted claim can be rejected.');

        $claim->update([
            'status' => ClaimStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $claim;
    }

    /**
     * Discard a draft claim, restoring the bill to what the patient owes.
     */
    public function void(Claim $claim, User $actor): void
    {
        $this->assertStatus($claim, [ClaimStatus::Draft], 'Only a draft claim can be discarded.');

        DB::transaction(function () use ($claim) {
            $bill = $claim->bill;

            Payment::query()->whereIn('id', array_filter([$claim->hmo_payment_id, $claim->waiver_payment_id]))->delete();

            $claim->delete();

            $this->billing->refreshStatus($bill);
        });
    }

    /**
     * The open schedule for a payer and month, created on first use.
     */
    public function openBatchFor(Payer $payer, string $period): ClaimBatch
    {
        $existing = ClaimBatch::query()
            ->where('payer_id', $payer->id)
            ->where('period', $period)
            ->where('status', ClaimBatchStatus::Open->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        $sequence = ClaimBatch::query()->where('payer_id', $payer->id)->where('period', $period)->count() + 1;

        return ClaimBatch::create([
            'batch_number' => "{$payer->code}/{$period}".($sequence > 1 ? "/{$sequence}" : ''),
            'payer_id' => $payer->id,
            'period' => $period,
            'status' => ClaimBatchStatus::Open,
        ]);
    }

    /**
     * Send a schedule to the payer.
     */
    public function submitBatch(ClaimBatch $batch, ?string $reference, User $actor, ?string $notes = null): ClaimBatch
    {
        if ($batch->status !== ClaimBatchStatus::Open) {
            throw ValidationException::withMessages(['status' => 'This schedule has already been submitted.']);
        }

        if ($batch->claims()->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'There are no claims in this schedule.']);
        }

        $batch->update([
            'status' => ClaimBatchStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'reference' => $reference,
            'notes' => $notes,
        ]);

        return $batch;
    }

    /**
     * Recompute the claim totals from its lines and keep the bill in step.
     */
    public function recalculate(Claim $claim, User $actor): void
    {
        $lines = $claim->lines()->get();

        $gross = round((float) $lines->sum('gross_amount'), 2);
        $amount = round((float) $lines->sum('amount'), 2);

        $claim->update([
            'gross_amount' => $gross,
            'discount_amount' => round($gross - $amount, 2),
            'copay_amount' => round((float) $lines->sum('copay_amount'), 2),
            'payer_amount' => round((float) $lines->sum('payer_amount'), 2),
        ]);

        $this->syncBillPayments($claim->refresh(), $actor);
    }

    /**
     * Turn a bill charge into a claim line under the payer's rules.
     *
     * @return array<string, mixed>
     */
    private function lineFor(BillCharge $charge, Payer $payer): array
    {
        $gross = round((float) $charge->total, 2);
        $amount = round($gross * (1 - $payer->discount_percent / 100), 2);
        $copay = $charge->source === BillCharge::SOURCE_PHARMACY
            ? round($amount * $payer->drug_copay_percent / 100, 2)
            : 0.0;

        return [
            'bill_charge_id' => $charge->id,
            'source' => $charge->source,
            'description' => $charge->description,
            'quantity' => $charge->quantity,
            'gross_amount' => $gross,
            'amount' => $amount,
            'copay_amount' => $copay,
            'payer_amount' => round($amount - $copay, 2),
            'is_covered' => true,
        ];
    }

    /**
     * Mirror the payer's share and the tariff discount as payments on the
     * bill, so its balance is what the enrollee owes.
     */
    private function syncBillPayments(Claim $claim, User $actor): void
    {
        $bill = $claim->bill;

        $claim->hmo_payment_id = $this->syncPayment(
            $bill, $claim->hmo_payment_id, PaymentMethod::Hmo, $claim->payer_amount, $claim->claim_number, $actor,
        );
        $claim->waiver_payment_id = $this->syncPayment(
            $bill, $claim->waiver_payment_id, PaymentMethod::Waiver, $claim->discount_amount, "Tariff discount — {$claim->claim_number}", $actor,
        );
        $claim->save();

        $this->billing->refreshStatus($bill);
    }

    private function syncPayment(Bill $bill, ?int $paymentId, PaymentMethod $method, float $amount, string $reference, User $actor): ?int
    {
        $payment = $paymentId ? Payment::find($paymentId) : null;

        if ($amount <= 0) {
            $payment?->delete();

            return null;
        }

        if ($payment) {
            $payment->update(['amount' => $amount, 'reference' => $reference]);

            return $payment->id;
        }

        return $bill->payments()->create([
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'received_by' => $actor->id,
        ])->id;
    }

    /**
     * The diagnosis from the visit's latest completed consultation: the ICD
     * coded lines payers ask for when they exist, else the clinical
     * impression.
     */
    private function diagnosisFor(Bill $bill): ?string
    {
        if (! $bill->visit_id) {
            return null;
        }

        $encounter = Encounter::query()
            ->where('visit_id', $bill->visit_id)
            ->consultations()
            ->signed()
            ->latest('signed_at')
            ->first();

        return $encounter?->diagnosisSummary();
    }

    /**
     * @param  array<int, ClaimStatus>  $allowed
     */
    private function assertStatus(Claim $claim, array $allowed, string $message): void
    {
        if (! in_array($claim->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }
}

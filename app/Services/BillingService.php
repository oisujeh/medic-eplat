<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillCharge;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the patient's running bill for a visit and posts charges to it.
 */
class BillingService
{
    /**
     * Find the visit's open bill, or open a new one. A patient with no active
     * visit falls back to a standalone bill.
     */
    public function openBillFor(Patient $patient, ?Visit $visit): Bill
    {
        if ($visit) {
            $existing = Bill::query()->where('visit_id', $visit->id)->open()->first();

            if ($existing) {
                return $existing;
            }
        }

        return Bill::create([
            'patient_id' => $patient->id,
            'visit_id' => $visit?->id,
            'status' => BillStatus::Open,
        ]);
    }

    /**
     * Post a charge to a bill. `unitPrice` is the price captured at the point
     * of service (e.g. the inventory selling price at dispense time).
     */
    public function postCharge(
        Bill $bill,
        string $source,
        string $description,
        int $quantity,
        float $unitPrice,
        User $actor,
        ?Model $reference = null,
    ): BillCharge {
        return $bill->charges()->create([
            'source' => $source,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Record a payment against a bill and re-evaluate its settlement status.
     */
    public function recordPayment(
        Bill $bill,
        float $amount,
        PaymentMethod $method,
        ?string $reference,
        User $actor,
    ): Payment {
        return DB::transaction(function () use ($bill, $amount, $method, $reference, $actor) {
            $payment = $bill->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'received_by' => $actor->id,
            ]);

            $this->refreshStatus($bill);

            return $payment;
        });
    }

    /**
     * Recompute a bill's status from its charges and payments.
     */
    public function refreshStatus(Bill $bill): void
    {
        if ($bill->status === BillStatus::Cancelled) {
            return;
        }

        $paid = (float) $bill->payments()->sum('amount');
        $total = (float) $bill->charges()->sum('total');

        $status = match (true) {
            $paid <= 0 => BillStatus::Open,
            $paid >= $total => BillStatus::Paid,
            default => BillStatus::PartiallyPaid,
        };

        if ($bill->status !== $status) {
            $bill->update(['status' => $status]);
        }
    }
}

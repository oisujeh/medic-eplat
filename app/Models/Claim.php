<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A claim to a payer for the services on one bill.
 *
 * @property int $id
 * @property string $claim_number
 * @property int $patient_id
 * @property int $payer_id
 * @property int $bill_id
 * @property int|null $visit_id
 * @property int|null $claim_batch_id
 * @property ClaimStatus $status
 * @property string|null $enrollee_number
 * @property string|null $plan
 * @property Carbon $service_date
 * @property string|null $diagnosis
 * @property string|null $authorization_code
 * @property Carbon|null $authorized_at
 * @property string|null $authorization_note
 * @property float $gross_amount
 * @property float $discount_amount
 * @property float $copay_amount
 * @property float $payer_amount
 * @property float|null $approved_amount
 * @property float $paid_amount
 * @property string|null $rejection_reason
 * @property int|null $hmo_payment_id
 * @property int|null $waiver_payment_id
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $remitted_at
 * @property string|null $remittance_reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'claim_number', 'patient_id', 'payer_id', 'bill_id', 'visit_id', 'claim_batch_id', 'status',
    'enrollee_number', 'plan', 'service_date', 'diagnosis',
    'authorization_code', 'authorized_at', 'authorization_note',
    'gross_amount', 'discount_amount', 'copay_amount', 'payer_amount', 'approved_amount', 'paid_amount',
    'rejection_reason', 'hmo_payment_id', 'waiver_payment_id',
    'created_by', 'submitted_by', 'submitted_at', 'remitted_at', 'remittance_reference', 'notes',
])]
class Claim extends Model implements AuditableRecord
{
    /** @use HasFactory<ClaimFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'service_date' => 'date',
            'authorized_at' => 'datetime',
            'gross_amount' => 'float',
            'discount_amount' => 'float',
            'copay_amount' => 'float',
            'payer_amount' => 'float',
            'approved_amount' => 'float',
            'paid_amount' => 'float',
            'submitted_at' => 'datetime',
            'remitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<ClaimBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ClaimBatch::class, 'claim_batch_id');
    }

    /**
     * @return HasMany<ClaimLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ClaimLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The payer's share still expected, after what has been remitted.
     */
    public function outstandingAmount(): float
    {
        if (! $this->status->isOutstanding()) {
            return 0.0;
        }

        return round(($this->approved_amount ?? $this->payer_amount) - $this->paid_amount, 2);
    }

    /**
     * The part of the claim the payer declined to approve.
     */
    public function shortfallAmount(): float
    {
        if ($this->approved_amount === null) {
            return 0.0;
        }

        return round(max(0, $this->payer_amount - $this->approved_amount), 2);
    }

    /**
     * Scope to claims still awaiting money from the payer.
     *
     * @param  Builder<Claim>  $query
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', [ClaimStatus::Submitted->value, ClaimStatus::PartiallyPaid->value]);
    }
}

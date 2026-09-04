<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property BillStatus $status
 */
#[Fillable(['patient_id', 'visit_id', 'status'])]
class Bill extends Model implements AuditableRecord
{
    /** @use HasFactory<BillFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillStatus::class,
        ];
    }

    /**
     * The total of all charges on the bill.
     */
    public function total(): float
    {
        return (float) $this->charges->sum('total');
    }

    /**
     * The total paid so far.
     */
    public function paidTotal(): float
    {
        return (float) $this->payments->sum('amount');
    }

    /**
     * The outstanding balance (may be negative when change is due).
     */
    public function balance(): float
    {
        return round($this->total() - $this->paidTotal(), 2);
    }

    /**
     * @return HasMany<BillCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(BillCharge::class)->latest();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    /**
     * Claims raised to a payer for charges on this bill.
     *
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class)->latest('id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Scope to bills still accepting charges.
     *
     * @param  Builder<Bill>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [BillStatus::Open->value, BillStatus::PartiallyPaid->value]);
    }
}

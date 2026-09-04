<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bill_id
 * @property string $source
 * @property string $description
 * @property int $quantity
 * @property float $unit_price
 * @property float $total
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'bill_id', 'source', 'description', 'quantity', 'unit_price', 'total',
    'reference_type', 'reference_id', 'created_by',
])]
class BillCharge extends Model implements AuditableRecord
{
    use Auditable;

    public const SOURCE_PHARMACY = 'pharmacy';

    public const SOURCE_LABORATORY = 'laboratory';

    public const SOURCE_CONSULTATION = 'consultation';

    /** Admission fee and bed days, posted by the admissions module. */
    public const SOURCE_ADMISSION = 'admission';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'total' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * The claim line this charge has been placed on, if any.
     *
     * @return HasOne<ClaimLine, $this>
     */
    public function claimLine(): HasOne
    {
        return $this->hasOne(ClaimLine::class);
    }

    /**
     * The patient is reached through the parent bill.
     */
    public function auditPatientId(): ?int
    {
        return $this->bill?->patient_id;
    }
}

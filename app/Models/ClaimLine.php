<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bill charge as it appears on a claim.
 *
 * @property int $id
 * @property int $claim_id
 * @property int $bill_charge_id
 * @property string $source
 * @property string $description
 * @property int $quantity
 * @property float $gross_amount
 * @property float $amount
 * @property float $copay_amount
 * @property float $payer_amount
 * @property bool $is_covered
 * @property float|null $approved_amount
 * @property string|null $remark
 */
#[Fillable([
    'claim_id', 'bill_charge_id', 'source', 'description', 'quantity',
    'gross_amount', 'amount', 'copay_amount', 'payer_amount', 'is_covered', 'approved_amount', 'remark',
])]
class ClaimLine extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'gross_amount' => 'float',
            'amount' => 'float',
            'copay_amount' => 'float',
            'payer_amount' => 'float',
            'is_covered' => 'boolean',
            'approved_amount' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Claim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * @return BelongsTo<BillCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(BillCharge::class, 'bill_charge_id');
    }

    /**
     * The patient is reached through the parent claim.
     */
    public function auditPatientId(): ?int
    {
        return $this->claim?->patient_id;
    }
}

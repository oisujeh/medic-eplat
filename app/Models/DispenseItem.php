<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dispense_id
 * @property int|null $inventory_item_id
 * @property int|null $medication_id
 * @property string $name
 * @property string|null $unit
 * @property int $quantity
 * @property float $unit_price
 * @property float $total
 */
#[Fillable([
    'dispense_id', 'inventory_item_id', 'medication_id', 'name', 'unit',
    'quantity', 'unit_price', 'total',
])]
class DispenseItem extends Model implements AuditableRecord
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
            'unit_price' => 'float',
            'total' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Dispense, $this>
     */
    public function dispense(): BelongsTo
    {
        return $this->belongsTo(Dispense::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<Medication, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    /**
     * The patient is reached through the parent dispense.
     */
    public function auditPatientId(): ?int
    {
        return $this->dispense?->patient_id;
    }
}

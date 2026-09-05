<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item on a requisition and how much of it was asked for, agreed,
 * sent and counted on arrival.
 *
 * @property int $id
 * @property int $stock_requisition_id
 * @property int $inventory_item_id
 * @property int $quantity_requested
 * @property int|null $quantity_approved
 * @property int $quantity_issued
 * @property int $quantity_received
 * @property string|null $note
 */
#[Fillable([
    'stock_requisition_id', 'inventory_item_id', 'quantity_requested',
    'quantity_approved', 'quantity_issued', 'quantity_received', 'note',
])]
class StockRequisitionLine extends Model implements AuditableRecord
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
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'quantity_issued' => 'integer',
            'quantity_received' => 'integer',
        ];
    }

    /**
     * The quantity the store will send: what was approved, else what was asked.
     */
    public function quantityToIssue(): int
    {
        return $this->quantity_approved ?? $this->quantity_requested;
    }

    /**
     * @return BelongsTo<StockRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function auditLabel(): string
    {
        return 'Requisition line #'.$this->getKey();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property string|null $batch_number
 * @property Carbon|null $expiry_date
 * @property int $quantity
 * @property float|null $cost_price
 * @property int|null $received_by
 * @property Carbon|null $received_at
 */
#[Fillable([
    'inventory_item_id', 'batch_number', 'expiry_date', 'quantity',
    'cost_price', 'received_by', 'received_at',
])]
class StockBatch extends Model implements AuditableRecord
{
    /** @use HasFactory<StockBatchFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'integer',
            'cost_price' => 'float',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Whether the batch is past its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

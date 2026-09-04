<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\StockBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Owns all stock quantity changes: receiving into batches, manual adjustments,
 * and FEFO issue — each recorded in the movement ledger with the on-hand total
 * kept in step.
 */
class InventoryService
{
    /**
     * Receive stock into a new batch.
     *
     * @param  array{batch_number?: string|null, expiry_date?: string|null, cost_price?: float|null}  $attributes
     */
    public function receiveStock(InventoryItem $item, int $quantity, array $attributes, User $actor): StockBatch
    {
        return DB::transaction(function () use ($item, $quantity, $attributes, $actor) {
            $batch = $item->batches()->create([
                'batch_number' => $attributes['batch_number'] ?? null,
                'expiry_date' => $attributes['expiry_date'] ?? null,
                'quantity' => $quantity,
                'cost_price' => $attributes['cost_price'] ?? null,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            $this->record($item, $batch, StockMovementType::Receipt, $quantity, $actor);
            $item->increment('quantity_on_hand', $quantity);

            return $batch;
        });
    }

    /**
     * Adjust a batch by a signed delta (stock count, damage, loss).
     */
    public function adjustBatch(StockBatch $batch, int $delta, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($batch, $delta, $reason, $actor) {
            if ($batch->quantity + $delta < 0) {
                throw new InsufficientStockException('Adjustment would take the batch below zero.');
            }

            $batch->increment('quantity', $delta);
            $this->record($batch->item, $batch, StockMovementType::Adjustment, $delta, $actor, reason: $reason);
            $batch->item->increment('quantity_on_hand', $delta);
        });
    }

    /**
     * Issue stock out, consuming batches first-expiry-first-out.
     */
    public function issue(InventoryItem $item, int $quantity, User $actor, ?Model $reference = null): void
    {
        DB::transaction(function () use ($item, $quantity, $actor, $reference) {
            $remaining = $quantity;

            foreach ($item->dispensableBatches()->lockForUpdate()->get() as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->quantity, $remaining);
                $batch->decrement('quantity', $take);
                $this->record($item, $batch, StockMovementType::Issue, -$take, $actor, reference: $reference);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InsufficientStockException("Insufficient stock to issue {$quantity} of {$item->name}.");
            }

            $item->decrement('quantity_on_hand', $quantity);
        });
    }

    /**
     * Append a movement to the ledger.
     */
    private function record(
        InventoryItem $item,
        ?StockBatch $batch,
        StockMovementType $type,
        int $change,
        User $actor,
        ?string $reason = null,
        ?Model $reference = null,
    ): void {
        $item->movements()->create([
            'stock_batch_id' => $batch?->id,
            'type' => $type,
            'quantity_change' => $change,
            'reason' => $reason,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $actor->id,
        ]);
    }
}

<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\StockBatch;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Owns all stock quantity changes: receiving into batches, manual
 * adjustments, FEFO issue and transfers between locations — each recorded in
 * the movement ledger with the on-hand total kept in step.
 */
class InventoryService
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly StockLocations $locations,
    ) {}

    /**
     * Receive stock into a new batch, at the given location or wherever
     * deliveries land.
     *
     * The batch is tagged with who paid for it (PRD STK-02), falling back to
     * the item's usual source.
     *
     * @param  array{batch_number?: string|null, expiry_date?: string|null, cost_price?: float|null, funding_source?: string|null}  $attributes
     */
    public function receiveStock(InventoryItem $item, int $quantity, array $attributes, User $actor, ?StockLocation $location = null): StockBatch
    {
        return DB::transaction(function () use ($item, $quantity, $attributes, $actor, $location) {
            $location ??= $this->locations->receiving();

            $batch = $item->batches()->create([
                'stock_location_id' => $location->id,
                'batch_number' => $attributes['batch_number'] ?? null,
                'expiry_date' => $attributes['expiry_date'] ?? null,
                'quantity' => $quantity,
                'cost_price' => $attributes['cost_price'] ?? null,
                'funding_source' => $attributes['funding_source'] ?? $item->funding_source?->value,
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
        DB::transaction(fn () => $this->audit->withReason($reason, function () use ($batch, $delta, $reason, $actor) {
            if ($batch->quantity + $delta < 0) {
                throw new InsufficientStockException('Adjustment would take the batch below zero.');
            }

            $batch->increment('quantity', $delta);
            $this->record($batch->item, $batch, StockMovementType::Adjustment, $delta, $actor, reason: $reason);
            $batch->item->increment('quantity_on_hand', $delta);
        }));
    }

    /**
     * Issue stock out to a patient or a ward, consuming batches
     * first-expiry-first-out at one location (or across all of them).
     */
    public function issue(InventoryItem $item, int $quantity, User $actor, ?Model $reference = null, ?StockLocation $from = null): void
    {
        DB::transaction(function () use ($item, $quantity, $actor, $reference, $from) {
            $this->consume($item, $quantity, $from, StockMovementType::Issue, $actor, $reference);
            $item->decrement('quantity_on_hand', $quantity);
        });
    }

    /**
     * Write stock off at a location (lost in transit, damaged on arrival),
     * consuming batches first-expiry-first-out.
     */
    public function writeOff(InventoryItem $item, int $quantity, StockLocation $at, ?string $reason, User $actor, ?Model $reference = null): void
    {
        DB::transaction(fn () => $this->audit->withReason($reason, function () use ($item, $quantity, $at, $reason, $actor, $reference) {
            $this->consume($item, $quantity, $at, StockMovementType::Adjustment, $actor, $reference, $reason);
            $item->decrement('quantity_on_hand', $quantity);
        }));
    }

    /**
     * Move stock between locations, batch by batch, first-expiry-first-out.
     * The facility's on-hand total is unchanged; each batch keeps its number,
     * expiry and cost at the destination.
     */
    public function transfer(InventoryItem $item, int $quantity, StockLocation $from, StockLocation $to, User $actor, ?Model $reference = null, ?string $reason = null): void
    {
        if ($from->is($to)) {
            throw new InsufficientStockException('A transfer needs two different locations.');
        }

        DB::transaction(function () use ($item, $quantity, $from, $to, $actor, $reference, $reason) {
            $remaining = $quantity;

            foreach ($item->dispensableBatches($from)->lockForUpdate()->get() as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($batch->quantity, $remaining);
                $batch->decrement('quantity', $take);
                $this->record($item, $batch, StockMovementType::TransferOut, -$take, $actor, $reason, $reference);

                $arrived = $this->batchAt($batch, $to, $actor);
                $arrived->increment('quantity', $take);
                $this->record($item, $arrived, StockMovementType::TransferIn, $take, $actor, $reason, $reference);

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new InsufficientStockException("Insufficient stock at {$from->name} to transfer {$quantity} of {$item->name}.");
            }
        });
    }

    /**
     * Take a quantity out of the batches at a location (or anywhere),
     * earliest expiry first, recording a movement of the given type.
     */
    private function consume(InventoryItem $item, int $quantity, ?StockLocation $from, StockMovementType $type, User $actor, ?Model $reference, ?string $reason = null): void
    {
        $remaining = $quantity;

        foreach ($item->dispensableBatches($from)->lockForUpdate()->get() as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($batch->quantity, $remaining);
            $batch->decrement('quantity', $take);
            $this->record($item, $batch, $type, -$take, $actor, $reason, $reference);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $where = $from ? " at {$from->name}" : '';

            throw new InsufficientStockException("Insufficient stock{$where} to issue {$quantity} of {$item->name}.");
        }
    }

    /**
     * The batch at the destination that a moving batch joins: the same
     * number, expiry and cost, created empty if it has not been seen there.
     */
    private function batchAt(StockBatch $source, StockLocation $to, User $actor): StockBatch
    {
        return $source->item->batches()->firstOrCreate(
            [
                'stock_location_id' => $to->id,
                'batch_number' => $source->batch_number,
                'expiry_date' => $source->expiry_date?->toDateString(),
            ],
            [
                'quantity' => 0,
                'cost_price' => $source->cost_price,
                'received_by' => $actor->id,
                'received_at' => now(),
            ],
        );
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
            'stock_location_id' => $batch?->stock_location_id,
            'type' => $type,
            'quantity_change' => $change,
            'reason' => $reason,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $actor->id,
        ]);
    }
}

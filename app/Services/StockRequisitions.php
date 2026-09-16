<?php

namespace App\Services;

use App\Enums\NumberKind;
use App\Enums\RequisitionStatus;
use App\Exceptions\RequisitionStateException;
use App\Models\InventoryItem;
use App\Models\StockLocation;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves stock between locations on request: the dispensary raises a
 * requisition, the store approves the quantities, issues them (which
 * transfers the batches), and the dispensary confirms what arrived.
 */
class StockRequisitions
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NumberSequences $numbers,
    ) {}

    /**
     * Raise a requisition from one location to another.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int, note?: string|null}>  $lines
     */
    public function create(StockLocation $to, StockLocation $from, array $lines, User $actor, ?string $note = null): StockRequisition
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('A requisition needs two different locations.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('A requisition needs at least one line.');
        }

        return DB::transaction(function () use ($to, $from, $lines, $actor, $note) {
            $requisition = StockRequisition::create([
                'number' => $this->numbers->next(NumberKind::Requisition),
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'status' => RequisitionStatus::Requested,
                'note' => $note,
                'requested_by' => $actor->id,
                'requested_at' => now(),
            ]);

            foreach ($lines as $line) {
                $requisition->lines()->create([
                    'inventory_item_id' => (int) $line['inventory_item_id'],
                    'quantity_requested' => (int) $line['quantity'],
                    'note' => $line['note'] ?? null,
                ]);
            }

            return $requisition->refresh();
        });
    }

    /**
     * Agree the quantities the store will send, line by line.
     *
     * @param  array<int, int>  $quantities  line id => quantity approved
     */
    public function approve(StockRequisition $requisition, array $quantities, User $actor): StockRequisition
    {
        $this->expect($requisition, RequisitionStatus::Requested, 'approved');

        return DB::transaction(function () use ($requisition, $quantities, $actor) {
            foreach ($requisition->lines as $line) {
                $line->update([
                    'quantity_approved' => max(0, (int) ($quantities[$line->id] ?? $line->quantity_requested)),
                ]);
            }

            $requisition->update([
                'status' => RequisitionStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $requisition->refresh();
        });
    }

    /**
     * Send the approved quantities: each line's stock moves from the
     * supplying location to the receiving one.
     */
    public function issue(StockRequisition $requisition, User $actor): StockRequisition
    {
        $this->expect($requisition, RequisitionStatus::Approved, 'issued');

        return DB::transaction(function () use ($requisition, $actor) {
            foreach ($requisition->lines as $line) {
                $quantity = $line->quantityToIssue();

                if ($quantity < 1) {
                    continue;
                }

                $this->inventory->transfer(
                    $line->item,
                    $quantity,
                    $requisition->fromLocation,
                    $requisition->toLocation,
                    $actor,
                    reference: $requisition,
                    reason: "Requisition {$requisition->number}",
                );

                $line->update(['quantity_issued' => $quantity]);
            }

            $requisition->update([
                'status' => RequisitionStatus::Issued,
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            return $requisition->refresh();
        });
    }

    /**
     * Confirm what arrived. Anything short of what was issued is written off
     * at the receiving location, so the ledger says where it went missing.
     *
     * @param  array<int, int>  $quantities  line id => quantity received
     */
    public function receive(StockRequisition $requisition, array $quantities, User $actor): StockRequisition
    {
        $this->expect($requisition, RequisitionStatus::Issued, 'received');

        return DB::transaction(function () use ($requisition, $quantities, $actor) {
            foreach ($requisition->lines as $line) {
                $received = min($line->quantity_issued, max(0, (int) ($quantities[$line->id] ?? $line->quantity_issued)));
                $shortfall = $line->quantity_issued - $received;

                if ($shortfall > 0) {
                    $this->inventory->writeOff(
                        $line->item,
                        $shortfall,
                        $requisition->toLocation,
                        "Short on requisition {$requisition->number}",
                        $actor,
                        $requisition,
                    );
                }

                $line->update(['quantity_received' => $received]);
            }

            $requisition->update([
                'status' => RequisitionStatus::Received,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            return $requisition->refresh();
        });
    }

    /**
     * Withdraw a requisition that has not yet been issued.
     */
    public function cancel(StockRequisition $requisition, ?string $reason, User $actor): StockRequisition
    {
        if (! $requisition->status->isCancellable()) {
            throw new RequisitionStateException("Requisition {$requisition->number} is {$requisition->status->label()} and cannot be cancelled.");
        }

        $requisition->update([
            'status' => RequisitionStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $requisition->refresh();
    }

    /**
     * What the supplying location holds of each line, for the approver.
     *
     * @return array<int, int> line id => quantity available
     */
    public function availability(StockRequisition $requisition): array
    {
        return $requisition->lines
            ->mapWithKeys(fn (StockRequisitionLine $line) => [
                $line->id => $line->item->dispensableQuantity($requisition->fromLocation),
            ])
            ->all();
    }

    private function expect(StockRequisition $requisition, RequisitionStatus $status, string $action): void
    {
        if ($requisition->status !== $status) {
            throw new RequisitionStateException(
                "Requisition {$requisition->number} is {$requisition->status->label()}; only a {$status->label()} requisition can be {$action}."
            );
        }
    }

    /**
     * Stocked items a requisition can ask for.
     *
     * @return Collection<int, InventoryItem>
     */
    public function requestableItems(): Collection
    {
        return InventoryItem::query()->active()->get();
    }
}

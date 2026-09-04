<?php

namespace App\Services;

use App\Models\BillCharge;
use App\Models\Dispense;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Dispenses prescribed items from stock: pulls each item from inventory (FEFO),
 * snapshots the selling price onto the dispense line, and posts the charge to
 * the patient's running bill — all atomically.
 */
class PharmacyService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly BillingService $billing,
    ) {}

    /**
     * @param  array<int, array{inventory_item_id: int, quantity: int, medication_id?: int|null}>  $lines
     */
    public function dispense(
        Patient $patient,
        ?Visit $visit,
        ?Encounter $encounter,
        ?QueueEntry $queueEntry,
        array $lines,
        User $actor,
        ?string $note = null,
    ): Dispense {
        return DB::transaction(function () use ($patient, $visit, $encounter, $queueEntry, $lines, $actor, $note) {
            $bill = $this->billing->openBillFor($patient, $visit);

            $dispense = Dispense::create([
                'patient_id' => $patient->id,
                'visit_id' => $visit?->id,
                'encounter_id' => $encounter?->id,
                'queue_entry_id' => $queueEntry?->id,
                'bill_id' => $bill->id,
                'dispensed_by' => $actor->id,
                'status' => Dispense::STATUS_DISPENSED,
                'note' => $note,
            ]);

            foreach ($lines as $line) {
                $item = InventoryItem::findOrFail($line['inventory_item_id']);
                $quantity = (int) $line['quantity'];
                $price = $item->selling_price;

                // Deduct from stock (FEFO) before capturing the priced line.
                $this->inventory->issue($item, $quantity, $actor, reference: $dispense);

                $dispenseItem = $dispense->items()->create([
                    'inventory_item_id' => $item->id,
                    'medication_id' => $line['medication_id'] ?? null,
                    'name' => $item->label(),
                    'unit' => $item->unit,
                    'quantity' => $quantity,
                    'unit_price' => $price,       // snapshot of the selling price
                    'total' => round($quantity * $price, 2),
                ]);

                $this->billing->postCharge(
                    bill: $bill,
                    source: BillCharge::SOURCE_PHARMACY,
                    description: "{$item->label()} × {$quantity}",
                    quantity: $quantity,
                    unitPrice: $price,
                    actor: $actor,
                    reference: $dispenseItem,
                );
            }

            return $dispense->refresh();
        });
    }
}

<?php

namespace App\Services;

use App\Enums\MedicationFulfilment;
use App\Events\MedicationDispensed;
use App\Models\BillCharge;
use App\Models\Dispense;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dispenses prescribed items from stock: pulls each item from the
 * dispensary (FEFO), snapshots the selling price onto the dispense line,
 * posts the charge to the patient's running bill and marks the prescription
 * as supplied — all atomically.
 */
class PharmacyService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly BillingService $billing,
        private readonly StockLocations $locations,
        private readonly DomainEvents $events,
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
        ?StockLocation $from = null,
    ): Dispense {
        return DB::transaction(function () use ($patient, $visit, $encounter, $queueEntry, $lines, $actor, $note, $from) {
            $from ??= $this->locations->forServicePoint($queueEntry?->servicePoint);
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
                $medication = $this->prescriptionFor($patient, $line['medication_id'] ?? null);

                // The prescription refuses more than it asked for, before stock moves.
                $medication?->recordDispensed($quantity);

                // Deduct from stock (FEFO) before capturing the priced line.
                $this->inventory->issue($item, $quantity, $actor, reference: $dispense, from: $from);

                $dispenseItem = $dispense->items()->create([
                    'inventory_item_id' => $item->id,
                    'medication_id' => $medication?->id,
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

            $dispense->refresh();
            $this->events->publish(new MedicationDispensed($dispense, $actor));

            return $dispense;
        });
    }

    /**
     * Note that the patient will obtain a prescription outside the facility,
     * so the pharmacy stops counting it as owed.
     */
    public function markExternal(Medication $medication, User $actor): Medication
    {
        $medication->update(['fulfilment' => MedicationFulfilment::External]);

        return $medication->refresh();
    }

    /**
     * Put a prescription marked as external back on the pharmacy's list.
     */
    public function reopen(Medication $medication): Medication
    {
        if ($medication->fulfilment === MedicationFulfilment::External) {
            $medication->update([
                'fulfilment' => $medication->quantity_dispensed > 0 && $medication->quantityRemaining() !== 0
                    ? MedicationFulfilment::Partial
                    : MedicationFulfilment::Pending,
            ]);
        }

        return $medication->refresh();
    }

    /**
     * The stocked products that can be supplied against a prescription: the
     * one the prescriber chose, else every product of the drug concept, else
     * whatever carries the drug's name.
     *
     * @return Collection<int, InventoryItem>
     */
    public function productsFor(Medication $medication): Collection
    {
        if ($medication->inventory_item_id !== null && $medication->product?->is_active) {
            return new Collection([$medication->product]);
        }

        if ($medication->concept_id !== null) {
            $products = InventoryItem::query()->productsOf($medication->concept_id)->get();

            if ($products->isNotEmpty()) {
                return $products;
            }
        }

        $name = trim($medication->name);

        if ($name === '') {
            return new Collection;
        }

        return InventoryItem::query()->active()
            ->where('name', 'like', '%'.$name.'%')
            ->get();
    }

    /**
     * The prescription a dispense line is filling, checked to be the
     * patient's own.
     */
    private function prescriptionFor(Patient $patient, ?int $medicationId): ?Medication
    {
        if ($medicationId === null) {
            return null;
        }

        $medication = Medication::query()->lockForUpdate()->findOrFail($medicationId);

        if ($medication->patient_id !== $patient->id) {
            throw new InvalidArgumentException('The prescription belongs to another patient.');
        }

        return $medication;
    }
}

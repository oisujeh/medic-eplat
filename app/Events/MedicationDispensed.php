<?php

namespace App\Events;

use App\Models\Dispense;
use App\Models\DispenseItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The pharmacy handed medicines to a patient.
 */
class MedicationDispensed extends DomainEvent
{
    public function __construct(public readonly Dispense $dispense, ?User $actor = null)
    {
        parent::__construct($actor);
    }

    public function subject(): Model
    {
        return $this->dispense;
    }

    public function payload(): array
    {
        return [
            'visit_id' => $this->dispense->visit_id,
            'items' => $this->dispense->items->map(fn (DispenseItem $item) => [
                'inventory_item_id' => $item->inventory_item_id,
                'medication_id' => $item->medication_id,
                'name' => $item->name,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ];
    }
}

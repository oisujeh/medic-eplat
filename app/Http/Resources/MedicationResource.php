<?php

namespace App\Http\Resources;

use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Medication
 */
class MedicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'name' => $this->name,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'quantity' => $this->quantity,
            'duration_days' => $this->duration_days,
            'status' => $this->status,
            'concept_id' => $this->concept_id,
            'inventory_item_id' => $this->inventory_item_id,
            'product_label' => $this->whenLoaded('product', fn () => $this->product?->label()),
            'fulfilment' => [
                'value' => $this->fulfilment->value,
                'label' => $this->fulfilment->label(),
                'tone' => $this->fulfilment->tone(),
            ],
            'quantity_dispensed' => $this->quantity_dispensed,
        ];
    }
}

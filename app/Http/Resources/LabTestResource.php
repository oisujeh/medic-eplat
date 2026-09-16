<?php

namespace App\Http\Resources;

use App\Models\LabTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalogue entry for the order picker. Expects `components_count`.
 *
 * @mixin LabTest
 */
class LabTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'department' => $this->department->value,
            'department_label' => $this->department->label(),
            'specimen' => $this->specimen_type,
            'is_panel' => $this->is_panel,
            'component_count' => $this->components_count ?? 0,
            'reference' => $this->referenceLabel(),
        ];
    }
}

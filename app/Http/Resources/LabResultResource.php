<?php

namespace App\Http\Resources;

use App\Models\LabResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LabResult
 */
class LabResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'value' => $this->value,
            'unit' => $this->unit,
            'display_value' => $this->displayValue(),
            'reference_range' => $this->reference_range,
            'flag' => $this->flag,
            'status' => $this->status,
            'specimen' => $this->specimen,
            'resulted_at' => $this->resulted_at?->diffForHumans(short: true),
        ];
    }
}

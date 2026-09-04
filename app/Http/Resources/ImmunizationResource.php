<?php

namespace App\Http\Resources;

use App\Models\Immunization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Immunization
 */
class ImmunizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'vaccine' => $this->vaccine,
            'dose_label' => $this->dose_label,
            'batch_no' => $this->batch_no,
            'site' => $this->site,
            'route' => $this->route,
            'administered_at' => $this->administered_at?->isoFormat('D MMM YYYY, h:mm a'),
        ];
    }
}

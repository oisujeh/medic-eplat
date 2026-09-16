<?php

namespace App\Http\Resources;

use App\Models\ServicePoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service point a patient can be routed to, with the staff who may be
 * assigned there.
 *
 * @mixin ServicePoint
 */
class ServicePointOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Set by the workflow engine on the step the pathway suggests.
            'is_default' => (bool) ($this->getAttribute('is_default') ?? false),
            'personnel' => $this->eligiblePersonnel()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
        ];
    }
}

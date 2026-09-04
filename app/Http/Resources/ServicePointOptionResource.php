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
            'personnel' => $this->eligiblePersonnel()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
        ];
    }
}

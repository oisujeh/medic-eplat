<?php

namespace App\Http\Resources;

use App\Models\EncounterAddendum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EncounterAddendum
 */
class EncounterAddendumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->author?->name,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'recorded_at_label' => $this->recorded_at->isoFormat('D MMM YYYY, h:mm a'),
        ];
    }
}

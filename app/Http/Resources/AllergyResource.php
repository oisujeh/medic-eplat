<?php

namespace App\Http\Resources;

use App\Models\Allergy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Allergy
 */
class AllergyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'substance' => $this->substance,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'category' => $this->category,
        ];
    }
}

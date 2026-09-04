<?php

namespace App\Http\Resources;

use App\Models\PatientAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientAlert
 */
class PatientAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }
}

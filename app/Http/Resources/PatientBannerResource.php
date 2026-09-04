<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Support\PatientOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The identity strip shown at the top of every clinical screen.
 *
 * @mixin Patient
 */
class PatientBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->fullName(),
            'initials' => $this->initials(),
            'file_number' => $this->file_number,
            'sex' => $this->sex,
            'sex_label' => PatientOptions::SEXES[$this->sex] ?? $this->sex,
            'age' => $this->age(),
            'dob' => $this->date_of_birth?->isoFormat('D MMM YYYY'),
            'phone' => $this->phone,
            'address' => $this->address,
            'url' => route('patients.show', $this->id),
        ];
    }
}

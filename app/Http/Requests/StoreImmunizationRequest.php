<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesEncounterRecords;
use Illuminate\Foundation\Http\FormRequest;

class StoreImmunizationRequest extends FormRequest
{
    use AuthorizesEncounterRecords;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'vaccine' => ['required', 'string', 'max:100'],
            'dose_label' => ['nullable', 'string', 'max:100'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'site' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

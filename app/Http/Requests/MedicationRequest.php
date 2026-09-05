<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesEncounterRecords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'concept_id' => ['nullable', 'integer', Rule::exists('concepts', 'id')->where('class', 'drug')],
            'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->where('is_active', true)],
            'dose' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}

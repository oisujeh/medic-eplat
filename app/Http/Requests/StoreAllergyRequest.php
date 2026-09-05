<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesEncounterRecords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllergyRequest extends FormRequest
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
            'substance' => ['required', 'string', 'max:255'],
            'concept_id' => ['nullable', 'integer', Rule::exists('concepts', 'id')->where('class', 'allergen')],
            'category' => ['nullable', Rule::in(['drug', 'food', 'environmental'])],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe'])],
        ];
    }
}

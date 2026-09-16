<?php

namespace App\Http\Requests;

use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'entry_mode' => ['sometimes', Rule::enum(FormEntryMode::class)],
            'encounter_types' => ['sometimes', 'array'],
            'encounter_types.*' => [Rule::enum(EncounterType::class)],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,slug'],
            'definition' => ['sometimes', 'array'],
            'definition.pages' => ['required_with:definition', 'array', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'definition.pages.required_with' => 'A form definition needs a "pages" list.',
            'definition.pages.min' => 'A form needs at least one page.',
        ];
    }
}

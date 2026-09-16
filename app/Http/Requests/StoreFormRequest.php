<?php

namespace App\Http\Requests;

use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'unique:forms,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'entry_mode' => ['nullable', Rule::enum(FormEntryMode::class)],
            'encounter_types' => ['nullable', 'array'],
            'encounter_types.*' => [Rule::enum(EncounterType::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,slug'],
        ];
    }
}

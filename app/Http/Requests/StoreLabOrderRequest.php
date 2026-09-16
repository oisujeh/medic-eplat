<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Http\Requests\Concerns\AuthorizesEncounterRecords;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabOrderRequest extends FormRequest
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
            'lab_test_ids' => ['array'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('is_active', true)],
            'name' => ['nullable', 'string', 'max:255'],
            'specimen' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'clinical_details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Require a catalogue test or a free-text one.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('lab_test_ids')) && ! $this->filled('name')) {
                $validator->errors()->add('name', 'Select a test from the catalogue or enter one to order.');
            }
        });
    }
}

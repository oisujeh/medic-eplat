<?php

namespace App\Http\Requests;

use App\Enums\ServiceCategory;
use App\Enums\WardType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:admissions` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('wards', 'code')],
            'type' => ['required', Rule::enum(WardType::class)],
            'bed_service_charge_id' => [
                'nullable',
                Rule::exists('service_charges', 'id')->where('category', ServiceCategory::Bed->value),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'initial_beds' => ['nullable', 'integer', 'min:0', 'max:200'],
            'bed_prefix' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The ward code may only contain letters, numbers and dashes.',
            'code.unique' => 'Another ward already uses that code.',
        ];
    }

    /**
     * Normalise the ward code before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\DischargeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DischargeAdmissionRequest extends FormRequest
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
            'discharge_type' => ['required', Rule::enum(DischargeType::class)],
            'discharge_summary' => ['nullable', 'string', 'max:10000'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:today'],
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
            'discharge_type.required' => 'Choose how the admission ended.',
            'follow_up_at.after_or_equal' => 'The follow-up date cannot be in the past.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Pregnancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Booking a pregnancy, and revising its booking details.
 */
class StorePregnancyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:maternity` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => [Rule::requiredIf(! $this->route('pregnancy')), 'nullable', Rule::exists('patients', 'id')],
            'lmp' => ['nullable', 'date', 'before_or_equal:today'],
            'edd' => ['nullable', 'date'],
            'gravida' => ['nullable', 'integer', 'min:1', 'max:20'],
            'para' => ['nullable', 'integer', 'min:0', 'max:20'],
            'booking_date' => ['nullable', 'date', 'before_or_equal:today'],
            'risk_factors' => ['nullable', 'array'],
            'risk_factors.*' => ['string', Rule::in(Pregnancy::RISK_FACTORS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Either the LMP or the EDD must be known.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('lmp') && ! $this->filled('edd')) {
                    $validator->errors()->add('lmp', 'Enter the last menstrual period or the expected date of delivery.');
                }

                if ($this->filled('gravida') && $this->filled('para') && (int) $this->input('para') >= (int) $this->input('gravida')) {
                    $validator->errors()->add('para', 'Para must be less than gravida for a current pregnancy.');
                }
            },
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
            'patient_id.required' => 'Choose the patient to book.',
            'lmp.before_or_equal' => 'The last menstrual period cannot be in the future.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\NigeriaLocations;
use App\Support\PatientOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessModule('registration');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_transfer' => $this->boolean('is_transfer'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', Rule::in(PatientOptions::TITLES)],
            'surname' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(array_keys(PatientOptions::SEXES))],
            'marital_status' => ['nullable', Rule::in(PatientOptions::MARITAL_STATUSES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'nationality' => ['required', 'string', 'max:100'],

            'state' => ['required', 'string', Rule::in(NigeriaLocations::states())],
            'lga' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! NigeriaLocations::isValidLga($this->input('state'), $value)) {
                        $fail('The selected LGA does not belong to the chosen state.');
                    }
                },
            ],

            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_relationship' => ['nullable', Rule::in(PatientOptions::NOK_RELATIONSHIPS)],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],

            'coverage' => ['required', Rule::in(array_keys(PatientOptions::COVERAGES))],
            'hmo_name' => ['nullable', 'required_if:coverage,hmo', 'string', 'max:150'],
            'hmo_number' => ['nullable', 'string', 'max:100'],

            'is_transfer' => ['boolean'],
            'transfer_from' => ['nullable', 'required_if:is_transfer,true', 'string', 'max:200'],
            'transfer_reason' => ['nullable', 'string', 'max:500'],
            'transfer_service' => ['nullable', 'string', 'max:500'],

            'visit_category' => ['required', Rule::in(PatientOptions::VISIT_CATEGORIES)],
            'outpatient_service' => ['nullable', 'required_if:visit_category,Outpatient', 'string', 'max:150'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hmo_name.required_if' => 'Please select the HMO / provider for HMO-covered patients.',
            'transfer_from.required_if' => 'Please enter the facility the patient is transferred from.',
            'outpatient_service.required_if' => 'Please select the outpatient service point.',
        ];
    }
}

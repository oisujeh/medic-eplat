<?php

namespace App\Http\Requests;

use App\Enums\IdentifierType;
use App\Models\Patient;
use App\Services\PatientIdentifiers;
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
        $nin = trim((string) $this->input('nin', ''));

        $this->merge([
            'is_transfer' => $this->boolean('is_transfer'),
            'dob_estimated' => $this->boolean('dob_estimated'),
            'nin' => $nin === '' ? null : IdentifierType::Nin->normalise($nin),
        ]);
    }

    /**
     * The patient being edited, when this is an update.
     */
    protected function editing(): ?Patient
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient ? $patient : null;
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
            'dob_estimated' => ['boolean'],
            'sex' => ['required', Rule::in(array_keys(PatientOptions::SEXES))],
            'marital_status' => ['nullable', Rule::in(PatientOptions::MARITAL_STATUSES)],
            'nin' => [
                'nullable',
                'digits:11',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $holder = app(PatientIdentifiers::class)->holderOf(IdentifierType::Nin, (string) $value);

                    if ($holder !== null && $holder->isNot($this->editing())) {
                        $fail("This NIN is already on record for {$holder->fullName()} ({$holder->file_number}). Open that record instead of registering again.");
                    }
                },
            ],
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
            'ward' => ['nullable', 'string', 'max:100'],

            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_relationship' => ['nullable', Rule::in(PatientOptions::NOK_RELATIONSHIPS)],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],

            'coverage' => ['required', Rule::in(array_keys(PatientOptions::COVERAGES))],
            // A payer on record supplies the provider name; otherwise it is typed.
            'payer_id' => ['nullable', Rule::exists('payers', 'id')->where('is_active', true)],
            'hmo_name' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('coverage') === 'hmo' && ! $this->filled('payer_id')),
                'string',
                'max:150',
            ],
            'hmo_number' => ['nullable', 'string', 'max:100'],
            'hmo_plan' => ['nullable', 'string', 'max:100'],
            'hmo_expires_at' => ['nullable', 'date'],

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
            'hmo_name.required' => 'Please select the HMO / provider for HMO-covered patients.',
            'transfer_from.required_if' => 'Please enter the facility the patient is transferred from.',
            'outpatient_service.required_if' => 'Please select the outpatient service point.',
        ];
    }
}

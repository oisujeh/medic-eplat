<?php

namespace App\Http\Requests;

use App\Enums\FacilityLevel;
use App\Enums\FacilityOwnership;
use App\Enums\TierProfile;
use App\Support\NigeriaLocations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacilityProfileRequest extends FormRequest
{
    /**
     * The optional profile fields, saved only when submitted so the
     * first-run wizard (which asks for fewer) leaves them untouched.
     *
     * @var list<string>
     */
    private const OPTIONAL = [
        'level', 'ownership', 'tier_profile', 'dhis2_org_unit', 'notice',
        'billing_enabled', 'patient_number_prefix', 'patient_number_check_digit',
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:administration` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/\-_.]*$/'],
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
            'level' => ['nullable', Rule::enum(FacilityLevel::class)],
            'ownership' => ['nullable', Rule::enum(FacilityOwnership::class)],
            'tier_profile' => ['nullable', Rule::enum(TierProfile::class)],
            // DHIS2 UIDs are eleven characters: a letter then letters or digits.
            'dhis2_org_unit' => ['nullable', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]{10}$/'],
            // The home-screen notice board. Optional; blank clears it.
            'notice' => ['nullable', 'string', 'max:500'],
            'billing_enabled' => ['sometimes', 'boolean'],
            'patient_number_prefix' => ['nullable', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/'],
            'patient_number_check_digit' => ['sometimes', 'boolean'],
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
            'name.required' => 'Enter the facility name.',
            'code.required' => 'Enter the facility code.',
            'code.regex' => 'The facility code may only contain letters, numbers, slashes, dashes, dots and underscores.',
            'state.required' => 'Select the state the facility is in.',
            'state.in' => 'Select a valid state.',
            'lga.required' => 'Select the local government area.',
            'dhis2_org_unit.regex' => 'A DHIS2 organisation unit UID is 11 characters: a letter followed by letters or digits.',
            'patient_number_prefix.regex' => 'The file number prefix may only contain letters and numbers.',
            'patient_number_prefix.max' => 'Keep the file number prefix to six characters or fewer.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'facility name',
            'code' => 'facility code',
            'lga' => 'LGA',
            'dhis2_org_unit' => 'DHIS2 org unit',
            'tier_profile' => 'profile',
        ];
    }

    /**
     * The validated facility profile: the identity fields plus whichever
     * optional fields were submitted.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $submitted = array_values(array_filter(self::OPTIONAL, fn (string $field) => $this->has($field)));

        return $this->safe()->only(['name', 'state', 'lga', 'code', ...$submitted]);
    }
}

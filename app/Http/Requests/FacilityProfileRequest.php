<?php

namespace App\Http\Requests;

use App\Support\NigeriaLocations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacilityProfileRequest extends FormRequest
{
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
            // The home-screen notice board. Optional; blank clears it.
            'notice' => ['nullable', 'string', 'max:500'],
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
        ];
    }

    /**
     * The validated facility profile.
     *
     * @return array{name: string, state: string, lga: string, code: string, notice?: string|null}
     */
    public function profile(): array
    {
        /** @var array{name: string, state: string, lga: string, code: string, notice?: string|null} */
        return $this->safe()->only(['name', 'state', 'lga', 'code', 'notice']);
    }
}

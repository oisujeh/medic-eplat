<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferAdmissionRequest extends FormRequest
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
            'bed_id' => ['required', Rule::exists('beds', 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
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
            'bed_id.required' => 'Choose the bed to move the patient to.',
        ];
    }
}

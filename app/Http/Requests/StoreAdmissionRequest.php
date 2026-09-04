<?php

namespace App\Http\Requests;

use App\Models\Bed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdmissionRequest extends FormRequest
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
            'patient_id' => ['required', Rule::exists('patients', 'id')],
            'admitting_diagnosis' => ['required', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'ward_id' => ['nullable', Rule::exists('wards', 'id')->where('is_active', true)],
            'bed_id' => ['nullable', Rule::exists('beds', 'id')],
            'attending_id' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    /**
     * A chosen bed must sit in the chosen ward.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('bed_id') || ! $this->filled('ward_id')) {
                    return;
                }

                $bed = Bed::find($this->integer('bed_id'));

                if ($bed && $bed->ward_id !== $this->integer('ward_id')) {
                    $validator->errors()->add('bed_id', 'The selected bed is not in the selected ward.');
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
            'patient_id.required' => 'Choose the patient to admit.',
            'admitting_diagnosis.required' => 'Enter the admitting diagnosis or reason for admission.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\PatientStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientStatusRequest extends FormRequest
{
    /**
     * Records staff move patients between statuses. Death is never set here:
     * it follows the death register.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessModule('registration');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                PatientStatus::Active->value,
                PatientStatus::TransferredOut->value,
                PatientStatus::LostToFollowUp->value,
            ])],
            'reason' => ['required', 'string', 'max:255'],
            'transferred_to' => ['nullable', 'required_if:status,'.PatientStatus::TransferredOut->value, 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'A death is recorded from the clinical record, not by changing the status.',
            'reason.required' => 'Say why the status is changing; it is kept with the record.',
            'transferred_to.required_if' => 'Name the facility the patient was transferred to.',
        ];
    }
}

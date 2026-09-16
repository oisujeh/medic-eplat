<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Encounter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralRequest extends FormRequest
{
    /**
     * Only someone who may document the encounter may refer from it.
     */
    public function authorize(): bool
    {
        $encounter = $this->route('encounter');

        return $encounter instanceof Encounter && (bool) $this->user()?->can('document', $encounter);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination_facility' => ['required', 'string', 'max:150'],
            'destination_department' => ['nullable', 'string', 'max:100'],
            'destination_contact' => ['nullable', 'string', 'max:150'],
            'urgency' => ['required', Rule::enum(Priority::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],
            'treatment_given' => ['nullable', 'string', 'max:2000'],
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
            'destination_facility.required' => 'Name the facility the patient is being referred to.',
            'reason.required' => 'State the reason for referral.',
        ];
    }
}

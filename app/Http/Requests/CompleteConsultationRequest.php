<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Http\Requests\Concerns\ClinicalDocumentationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CompleteConsultationRequest extends FormRequest
{
    use ClinicalDocumentationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string|Enum>>
     */
    public function rules(): array
    {
        return [
            // Clinical documentation
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['required', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:10000'],

            // Structured stage data + outcome / follow-up
            ...$this->structuredRules(),

            // Disposition (optional onward routing)
            'next_service_point_id' => ['nullable', Rule::exists('service_points', 'id')->where('is_active', true)],
            'next_assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'next_priority' => ['nullable', new Enum(Priority::class)],
            'next_note' => ['nullable', 'string', 'max:500'],
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
            'diagnosis.required' => 'A working diagnosis (or impression) is required to complete the consultation.',
        ];
    }
}

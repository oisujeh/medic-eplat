<?php

namespace App\Http\Requests;

use App\Enums\DataQualitySeverity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDataQualityRuleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `permission:data-quality.manage_rules`
     * middleware. The thresholds themselves are the check's own, so only
     * their shape is checked here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'severity' => ['required', Rule::enum(DataQualitySeverity::class)],
            'owner_role' => ['nullable', 'string', 'exists:roles,slug'],
            'is_active' => ['required', 'boolean'],
            'parameters' => ['nullable', 'array'],
            'parameters.*' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $parameters = $this->input('parameters');

        if (is_array($parameters)) {
            // Numbers arrive from the form as numbers; the rule above reads
            // every threshold as a string so one shape covers both.
            $this->merge(['parameters' => array_map(
                fn ($value) => $value === null ? null : (string) $value,
                $parameters,
            )]);
        }
    }
}

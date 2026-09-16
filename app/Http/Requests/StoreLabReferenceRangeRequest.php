<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One reference band for a test: the age span and sex it applies to, and
 * its normal and critical limits (PRD LAB-02).
 */
class StoreLabReferenceRangeRequest extends FormRequest
{
    /**
     * Authorization is handled by the `permission:laboratory.manage_catalogue` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sex' => ['nullable', Rule::in(['F', 'M'])],
            'age_min_days' => ['required', 'integer', 'min:0'],
            'age_max_days' => ['nullable', 'integer', 'gte:age_min_days'],
            'low' => ['nullable', 'numeric'],
            'high' => ['nullable', 'numeric'],
            'critical_low' => ['nullable', 'numeric'],
            'critical_high' => ['nullable', 'numeric'],
            'text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A band must say something, and its limits must sit in order.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $low = $this->numeric('low');
            $high = $this->numeric('high');
            $criticalLow = $this->numeric('critical_low');
            $criticalHigh = $this->numeric('critical_high');

            if ($low === null && $high === null && ! $this->filled('text')) {
                $validator->errors()->add('low', 'Give a lower or upper limit, or a qualitative reference.');
            }

            if ($low !== null && $high !== null && $high < $low) {
                $validator->errors()->add('high', 'The upper limit must not be below the lower limit.');
            }

            if ($criticalLow !== null && $low !== null && $criticalLow > $low) {
                $validator->errors()->add('critical_low', 'The critical low must not be above the normal lower limit.');
            }

            if ($criticalHigh !== null && $high !== null && $criticalHigh < $high) {
                $validator->errors()->add('critical_high', 'The critical high must not be below the normal upper limit.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'age_min_days' => 'age from',
            'age_max_days' => 'age to',
        ];
    }

    private function numeric(string $field): ?float
    {
        return $this->filled($field) && is_numeric($this->input($field)) ? (float) $this->input($field) : null;
    }
}

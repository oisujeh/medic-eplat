<?php

namespace App\Http\Requests;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveillanceCaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:surveillance` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::enum(CaseClassification::class)],
            'outcome' => ['required', Rule::enum(CaseOutcome::class)],
            'onset_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

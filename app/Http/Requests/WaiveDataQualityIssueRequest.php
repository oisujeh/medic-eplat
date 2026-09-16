<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WaiveDataQualityIssueRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `permission:data-quality.waive_issues`
     * middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
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
            'reason.required' => 'Say why this record is being left as it is.',
            'reason.min' => 'Give enough of a reason that somebody reading it later understands the decision.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidFormSubmissionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Say why the form is being voided.',
            'reason.min' => 'Give a fuller reason for voiding the form.',
        ];
    }
}

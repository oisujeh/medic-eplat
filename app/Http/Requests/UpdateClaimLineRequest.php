<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClaimLineRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:claims` middleware.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0'],
            'copay_amount' => ['nullable', 'numeric', 'min:0'],
            'is_covered' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}

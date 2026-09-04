<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClaimRequest extends FormRequest
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
            'bill_id' => ['required', Rule::exists('bills', 'id')],
            'charge_ids' => ['nullable', 'array'],
            'charge_ids.*' => ['integer', Rule::exists('bill_charges', 'id')->where('bill_id', $this->input('bill_id'))],
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
            'bill_id.required' => 'Choose the bill to claim for.',
        ];
    }
}

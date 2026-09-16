<?php

namespace App\Http\Requests;

use App\Enums\PayerType;
use App\Models\Payer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayerRequest extends FormRequest
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
        /** @var Payer $payer */
        $payer = $this->route('payer');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('payers', 'code')->ignore($payer->id)],
            'type' => ['required', Rule::enum(PayerType::class)],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'drug_copay_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
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
            'code.regex' => 'The payer code may only contain letters, numbers and dashes.',
            'code.unique' => 'Another payer already uses that code.',
        ];
    }

    /**
     * Normalise the payer code before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEncounterRequest extends FormRequest
{
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
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'history' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:10000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

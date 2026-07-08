<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CompleteQueueEntryRequest extends FormRequest
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
     * @return array<string, array<int, ValidationRule|string|Enum>>
     */
    public function rules(): array
    {
        return [
            'next_service_point_id' => ['nullable', Rule::exists('service_points', 'id')->where('is_active', true)],
            'next_assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'next_priority' => ['nullable', new Enum(Priority::class)],
            'next_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}

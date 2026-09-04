<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Support\PatientOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RoutePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessModule('queues');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string|Enum>>
     */
    public function rules(): array
    {
        return [
            'service_point_id' => ['required', Rule::exists('service_points', 'id')->where('is_active', true)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'priority' => ['required', new Enum(Priority::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'visit_reason' => ['nullable', Rule::in(PatientOptions::VISIT_REASONS)],
        ];
    }
}

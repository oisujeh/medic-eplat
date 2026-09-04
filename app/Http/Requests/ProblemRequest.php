<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesEncounterRecords;
use App\Models\Problem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProblemRequest extends FormRequest
{
    use AuthorizesEncounterRecords;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([Problem::STATUS_ACTIVE, Problem::STATUS_CHRONIC, Problem::STATUS_RESOLVED])],
            'role' => ['nullable', Rule::in([Problem::ROLE_PRIMARY, Problem::ROLE_SECONDARY, Problem::ROLE_DIFFERENTIAL])],
        ];
    }
}

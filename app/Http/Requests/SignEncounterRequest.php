<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Http\Requests\Concerns\EncounterDocumentationRules;
use App\Models\Encounter;
use App\Models\Problem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignEncounterRequest extends FormRequest
{
    use EncounterDocumentationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sign', $this->encounter());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->documentationRules($this->encounter()->type),

            // Disposition (optional onward routing)
            'next_service_point_id' => ['nullable', Rule::exists('service_points', 'id')->where('is_active', true)],
            'next_assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'next_priority' => ['nullable', Rule::enum(Priority::class)],
            'next_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A consultation needs a diagnosis before it can be signed: a coded
     * primary or secondary line, or a written impression.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $encounter = $this->encounter();

            if (! $encounter->type->isConsultation() || $this->filled('assessment')) {
                return;
            }

            $coded = $encounter->problems()
                ->whereIn('role', [Problem::ROLE_PRIMARY, Problem::ROLE_SECONDARY])
                ->exists();

            if (! $coded) {
                $validator->errors()->add('assessment', 'A diagnosis is required to sign the consultation — code one or write an impression.');
            }
        });
    }

    public function encounter(): Encounter
    {
        /** @var Encounter */
        return $this->route('encounter');
    }
}

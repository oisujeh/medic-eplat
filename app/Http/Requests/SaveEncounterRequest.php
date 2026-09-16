<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\EncounterDocumentationRules;
use App\Models\Encounter;
use Illuminate\Foundation\Http\FormRequest;

class SaveEncounterRequest extends FormRequest
{
    use EncounterDocumentationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('document', $this->encounter());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->documentationRules($this->encounter()->type);
    }

    public function encounter(): Encounter
    {
        /** @var Encounter */
        return $this->route('encounter');
    }
}

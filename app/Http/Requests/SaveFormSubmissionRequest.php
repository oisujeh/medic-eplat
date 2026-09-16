<?php

namespace App\Http\Requests;

use App\Enums\FormEntryMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The answers on a form, saved as the user types or on completion. The
 * answers themselves are checked against the form's own definition by the
 * engine; here only the envelope is validated.
 */
class SaveFormSubmissionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'data' => ['present', 'array'],
            'entry_mode' => ['nullable', Rule::enum(FormEntryMode::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        return $this->validated('data') ?? [];
    }

    public function entryMode(): ?FormEntryMode
    {
        $mode = $this->validated('entry_mode');

        return $mode ? FormEntryMode::from($mode) : null;
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * A form file pasted or uploaded for import: the JSON the designer exports.
 */
class ImportFormRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required_without:json', 'file', 'max:2048'],
            'json' => ['required_without:file', 'nullable', 'string', 'max:1000000'],
        ];
    }

    /**
     * The decoded form file.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException when the content is not JSON
     */
    public function payload(): array
    {
        $raw = $this->hasFile('file')
            ? (string) file_get_contents((string) $this->file('file')->getRealPath())
            : (string) $this->validated('json');

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['file' => 'The file is not valid JSON.']);
        }

        return $decoded;
    }
}

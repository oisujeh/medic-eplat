<?php

namespace App\Http\Requests;

use App\Enums\BedStatus;
use App\Models\Bed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBedRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Authorization is handled by the `module:admissions` middleware. A bed is
     * only ever switched between available and out of service here; occupancy
     * follows admissions.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Bed $bed */
        $bed = $this->route('bed');

        return [
            'status' => ['required', Rule::in([BedStatus::Available->value, BedStatus::OutOfService->value])],
            'label' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('beds', 'label')->where('ward_id', $bed->ward_id)->ignore($bed->id),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

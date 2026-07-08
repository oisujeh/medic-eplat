<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreVitalSignsRequest extends FormRequest
{
    /**
     * The measurement fields — at least one must be provided.
     *
     * @var array<int, string>
     */
    public const MEASUREMENTS = [
        'temperature_c', 'systolic_bp', 'diastolic_bp', 'pulse_bpm', 'respiratory_rate',
        'spo2', 'blood_glucose', 'pain_score', 'weight_kg', 'height_cm',
        'muac_cm', 'head_circumference_cm',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request. Ranges are generous
     * sanity bounds to catch fat-finger entry, not clinical thresholds.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'temperature_c' => ['nullable', 'numeric', 'between:25,45'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'pulse_bpm' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,90'],
            'spo2' => ['nullable', 'integer', 'between:40,100'],
            'blood_glucose' => ['nullable', 'numeric', 'between:1,50'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.3,500'],
            'height_cm' => ['nullable', 'numeric', 'between:15,260'],
            'muac_cm' => ['nullable', 'numeric', 'between:5,60'],
            'head_circumference_cm' => ['nullable', 'numeric', 'between:20,70'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Require that at least one measurement was entered.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasMeasurement = collect(self::MEASUREMENTS)
                ->contains(fn (string $field) => $this->filled($field));

            if (! $hasMeasurement) {
                $validator->errors()->add('temperature_c', 'Record at least one vital sign or measurement.');
            }
        });
    }
}

<?php

namespace Database\Factories;

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;
use App\Models\ObservationSet;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObservationSet>
 */
class ObservationSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'alert_level' => AlertLevel::Normal,
            'recorded_at' => now(),
        ];
    }

    /**
     * Attach readings keyed by ObservationCode value, uninterpreted.
     *
     * @param  array<string, float|string>  $readings
     */
    public function withReadings(array $readings): static
    {
        return $this->afterCreating(function (ObservationSet $set) use ($readings) {
            foreach ($readings as $value => $reading) {
                $code = ObservationCode::from($value);

                $set->observations()->create([
                    'patient_id' => $set->patient_id,
                    'code' => $code,
                    'value' => $code->isText() ? null : $reading,
                    'text_value' => $code->isText() ? $reading : null,
                    'unit' => $code->unit(),
                    'recorded_at' => $set->recorded_at,
                ]);
            }
        });
    }
}

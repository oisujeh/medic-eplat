<?php

namespace Database\Factories;

use App\Models\LabResult;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabResult>
 */
class LabResultFactory extends Factory
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
            'name' => fake()->randomElement(['Haemoglobin', 'CD4 Count', 'Viral Load', 'Fasting Blood Sugar']),
            'value' => (string) fake()->numberBetween(1, 900),
            'unit' => fake()->randomElement(['g/dL', 'cells/mm3', 'copies/mL', 'mmol/L']),
            'reference_range' => fake()->randomElement(['13-17', '500-1500', '<50', '4-7']),
            'flag' => fake()->randomElement(['normal', 'low', 'high', 'critical']),
            'status' => LabResult::STATUS_RESULTED,
            'resulted_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}

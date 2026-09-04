<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medication>
 */
class MedicationFactory extends Factory
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
            'name' => fake()->randomElement(['TLD', 'Amlodipine', 'Metformin', 'Lisinopril', 'Paracetamol']),
            'dose' => fake()->randomElement(['5mg', '10mg', '500mg', '1 tablet']),
            'frequency' => fake()->randomElement(['OD', 'BD', 'TDS', 'PRN']),
            'route' => fake()->randomElement(['PO', 'IV', 'IM']),
            'status' => Medication::STATUS_ACTIVE,
            'started_at' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * A medication the patient is currently taking.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => Medication::STATUS_ACTIVE]);
    }
}

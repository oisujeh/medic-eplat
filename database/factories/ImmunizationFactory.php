<?php

namespace Database\Factories;

use App\Models\Immunization;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Immunization>
 */
class ImmunizationFactory extends Factory
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
            'vaccine' => fake()->randomElement(['BCG', 'OPV', 'Penta', 'Measles', 'Yellow Fever', 'Hepatitis B']),
            'dose_label' => fake()->randomElement(['Birth', 'OPV 1', 'OPV 2', 'Booster', '9 months']),
            'batch_no' => fake()->optional()->bothify('B-####'),
            'site' => fake()->randomElement(['Left thigh', 'Right thigh', 'Left arm', 'Oral']),
            'route' => fake()->randomElement(['IM', 'PO', 'SC', 'ID']),
            'administered_at' => now(),
        ];
    }
}

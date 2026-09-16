<?php

namespace Database\Factories;

use App\Models\Allergy;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allergy>
 */
class AllergyFactory extends Factory
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
            'substance' => fake()->randomElement(['Penicillin', 'Sulfa drugs', 'Aspirin', 'Peanuts', 'Latex']),
            'category' => fake()->randomElement(['drug', 'food', 'environmental']),
            'reaction' => fake()->randomElement(['Rash', 'Hives', 'Swelling', 'Anaphylaxis']),
            'severity' => fake()->randomElement(['mild', 'moderate', 'severe']),
            'status' => Allergy::STATUS_ACTIVE,
            'noted_at' => fake()->optional()->dateTimeBetween('-3 years', 'now'),
        ];
    }
}

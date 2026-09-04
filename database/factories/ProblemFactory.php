<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Problem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Problem>
 */
class ProblemFactory extends Factory
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
            'name' => fake()->randomElement(['Hypertension', 'Type 2 Diabetes', 'HIV', 'Asthma', 'Peptic ulcer disease']),
            'code' => fake()->optional()->bothify('?##.#'),
            'status' => fake()->randomElement([Problem::STATUS_ACTIVE, Problem::STATUS_CHRONIC, Problem::STATUS_RESOLVED]),
            'onset_date' => fake()->optional()->dateTimeBetween('-8 years', '-1 month'),
        ];
    }

    /**
     * A currently active problem.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => Problem::STATUS_ACTIVE]);
    }
}

<?php

namespace Database\Factories;

use App\Models\GlobalProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalProperty>
 */
class GlobalPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word().'.'.fake()->word(),
            'value' => fake()->words(3, true),
            'description' => null,
        ];
    }
}

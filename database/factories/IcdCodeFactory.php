<?php

namespace Database\Factories;

use App\Models\IcdCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IcdCode>
 */
class IcdCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('?##.#')),
            'description' => fake()->sentence(3),
            'chapter' => null,
            'is_active' => true,
        ];
    }
}

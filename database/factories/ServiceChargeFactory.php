<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use App\Models\ServiceCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCharge>
 */
class ServiceChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SVC-###')),
            'name' => fake()->unique()->words(2, true),
            'category' => fake()->randomElement(ServiceCategory::cases()),
            'unit' => fake()->randomElement(['per visit', 'per day', 'each', null]),
            'price' => fake()->randomFloat(2, 500, 50000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}

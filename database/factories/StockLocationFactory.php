<?php

namespace Database\Factories;

use App\Enums\StockLocationKind;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLocation>
 */
class StockLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' store';

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => ucfirst($name),
            'kind' => StockLocationKind::Store,
            'receives_deliveries' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A dispensary that issues to patients.
     */
    public function dispensary(): static
    {
        return $this->state(fn () => ['kind' => StockLocationKind::Dispensary]);
    }

    /**
     * The store where supplier deliveries land.
     */
    public function receiving(): static
    {
        return $this->state(fn () => ['kind' => StockLocationKind::Store, 'receives_deliveries' => true]);
    }
}

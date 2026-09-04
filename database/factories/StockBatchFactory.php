<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'batch_number' => strtoupper(fake()->bothify('B####')),
            'expiry_date' => fake()->dateTimeBetween('+3 months', '+2 years')->format('Y-m-d'),
            'quantity' => fake()->numberBetween(10, 200),
            'cost_price' => fake()->randomFloat(2, 5, 200),
            'received_at' => now(),
        ];
    }
}

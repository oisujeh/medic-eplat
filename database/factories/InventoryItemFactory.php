<?php

namespace Database\Factories;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 5, 200);

        return [
            'code' => strtoupper(fake()->unique()->bothify('DRG-####')),
            'name' => fake()->unique()->randomElement([
                'Amlodipine', 'Metformin', 'Paracetamol', 'Amoxicillin',
                'Lisinopril', 'Artemether/Lumefantrine', 'Omeprazole', 'Ibuprofen',
            ]),
            'category' => InventoryCategory::Drug,
            'form' => fake()->randomElement(['Tablet', 'Capsule', 'Injection']),
            'strength' => fake()->randomElement(['5mg', '500mg', '250mg']),
            'unit' => 'tablet',
            'cost_price' => $cost,
            'selling_price' => round($cost * 1.5, 2),
            'reorder_level' => 20,
            'quantity_on_hand' => 0,
            'is_active' => true,
        ];
    }
}

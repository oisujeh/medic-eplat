<?php

namespace Database\Factories;

use App\Enums\LabDepartment;
use App\Models\LabTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabTest>
 */
class LabTestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $low = fake()->randomFloat(1, 1, 10);

        return [
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'name' => fake()->unique()->words(2, true),
            'department' => fake()->randomElement(LabDepartment::cases()),
            'specimen_type' => fake()->randomElement(['Serum', 'EDTA blood', 'Urine', 'Plasma']),
            'unit' => fake()->randomElement(['g/dL', 'mmol/L', 'cells/mm3', 'U/L']),
            'reference_low' => $low,
            'reference_high' => $low + fake()->randomFloat(1, 1, 20),
            'price' => fake()->randomFloat(2, 500, 20000),
            'turnaround_hours' => fake()->randomElement([2, 4, 24, 48]),
            'is_panel' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A panel (bundle of component analytes).
     */
    public function panel(): static
    {
        return $this->state(fn () => [
            'is_panel' => true,
            'reference_low' => null,
            'reference_high' => null,
            'unit' => null,
        ]);
    }
}

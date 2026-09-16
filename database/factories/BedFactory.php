<?php

namespace Database\Factories;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bed>
 */
class BedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ward_id' => Ward::factory(),
            'label' => 'Bed '.fake()->unique()->numberBetween(1, 999),
            'status' => BedStatus::Available,
            'sort_order' => 0,
            'notes' => null,
        ];
    }

    /**
     * A bed that cannot take a patient.
     */
    public function outOfService(): static
    {
        return $this->state(fn () => ['status' => BedStatus::OutOfService]);
    }
}

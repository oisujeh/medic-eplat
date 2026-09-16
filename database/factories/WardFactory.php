<?php

namespace Database\Factories;

use App\Enums\WardType;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Male Medical', 'Female Medical', 'Paediatric', 'Surgical', 'Maternity']).' Ward',
            'code' => strtoupper(fake()->unique()->lexify('W???')),
            'type' => WardType::General,
            'bed_service_charge_id' => null,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Create the ward with the given number of available beds.
     */
    public function withBeds(int $count): static
    {
        return $this->afterCreating(function (Ward $ward) use ($count) {
            for ($i = 1; $i <= $count; $i++) {
                $ward->beds()->create(['label' => "Bed {$i}", 'sort_order' => $i]);
            }
        });
    }
}

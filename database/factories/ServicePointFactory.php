<?php

namespace Database\Factories;

use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServicePoint>
 */
class ServicePointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['General Consultation', 'ANC Clinic', 'Family Planning', 'Immunization', 'Dental'])
            .' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'icon' => 'Stethoscope',
            'module_slug' => 'clinical',
            'captures_vitals' => false,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}

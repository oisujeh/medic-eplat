<?php

namespace Database\Factories;

use App\Models\ProviderSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSchedule>
 */
class ProviderScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => User::factory(),
            'service_point_id' => null,
            'weekday' => fake()->numberBetween(1, 5),
            'start_time' => '09:00',
            'end_time' => '16:00',
            'slot_minutes' => 30,
            'is_active' => true,
        ];
    }
}

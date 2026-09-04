<?php

namespace Database\Factories;

use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('+1 hour', '+1 week'))->startOfHour();

        return [
            'provider_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addHour(),
            'reason' => fake()->optional()->randomElement(['Leave', 'Meeting', 'Lunch', 'Training']),
        ];
    }
}

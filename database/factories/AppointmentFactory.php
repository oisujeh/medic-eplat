<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\Priority;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('+1 hour', '+2 weeks'))->startOfHour();
        $duration = 30;

        return [
            'patient_id' => Patient::factory(),
            'provider_id' => User::factory(),
            'service_point_id' => ServicePoint::factory(),
            'scheduled_start' => $start,
            'scheduled_end' => (clone $start)->addMinutes($duration),
            'duration_minutes' => $duration,
            'status' => AppointmentStatus::Scheduled,
            'source' => AppointmentSource::Booked,
            'priority' => Priority::Normal,
            'reason' => fake()->optional()->randomElement(['Follow-up', 'New complaint', 'Review', 'Results review']),
            'note' => null,
        ];
    }

    /**
     * Book at a specific time for a given provider/service point.
     */
    public function at(Carbon $start, int $duration = 30): static
    {
        return $this->state(fn () => [
            'scheduled_start' => $start,
            'scheduled_end' => (clone $start)->addMinutes($duration),
            'duration_minutes' => $duration,
        ]);
    }
}

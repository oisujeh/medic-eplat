<?php

namespace Database\Factories;

use App\Enums\PregnancyStatus;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pregnancy>
 */
class PregnancyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lmp = now()->subWeeks(fake()->numberBetween(8, 36))->startOfDay();

        return [
            'pregnancy_number' => 'PRG/'.now()->year.'/'.Str::upper(Str::random(6)),
            'patient_id' => Patient::factory()->state(['sex' => 'F']),
            'status' => PregnancyStatus::Active,
            'lmp' => $lmp->toDateString(),
            'edd' => Pregnancy::eddFromLmp($lmp)->toDateString(),
            'gravida' => fake()->numberBetween(1, 5),
            'para' => fake()->numberBetween(0, 4),
            'booking_date' => now()->toDateString(),
            'booked_by' => User::factory(),
            'risk_factors' => [],
        ];
    }
}

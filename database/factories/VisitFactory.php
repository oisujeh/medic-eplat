<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'visit_number' => 'V/'.now()->year.'/'.fake()->unique()->numerify('######'),
            'status' => VisitStatus::Open,
            'opened_at' => now(),
        ];
    }

    /**
     * A closed visit.
     */
    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => VisitStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\ServicePoint;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
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
            'visit_id' => fn (array $attributes) => Visit::factory()->create(['patient_id' => $attributes['patient_id']])->id,
            'type' => EncounterType::Consultation,
            'status' => EncounterStatus::InProgress,
            'presenting_complaint' => fake()->optional()->sentence(),
            'started_at' => now(),
        ];
    }

    /**
     * A signed-off encounter.
     */
    public function signed(): static
    {
        return $this->state(fn () => [
            'status' => EncounterStatus::Signed,
            'signed_at' => now(),
        ]);
    }

    /**
     * A nursing encounter held at the service point with the given slug.
     */
    public function nursing(string $servicePointSlug = 'anc'): static
    {
        return $this->state(fn () => [
            'type' => $servicePointSlug === 'triage' ? EncounterType::Triage : EncounterType::Nursing,
            'service_point_id' => ServicePoint::firstOrCreate(
                ['slug' => $servicePointSlug],
                ['name' => ucfirst($servicePointSlug), 'module_slug' => 'nursing', 'is_active' => true, 'sort_order' => 0],
            )->id,
        ]);
    }
}

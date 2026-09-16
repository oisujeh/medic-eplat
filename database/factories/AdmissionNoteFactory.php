<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\AdmissionNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionNote>
 */
class AdmissionNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'patient_id' => fn (array $attributes) => Admission::find($attributes['admission_id'])?->patient_id,
            'author_id' => User::factory(),
            'type' => AdmissionNote::TYPE_PROGRESS,
            'note' => fake()->sentence(12),
            'recorded_at' => now(),
        ];
    }
}

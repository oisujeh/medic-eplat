<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAlert>
 */
class PatientAlertFactory extends Factory
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
            'type' => fake()->randomElement(['clinical', 'administrative', 'safety']),
            'severity' => fake()->randomElement([
                PatientAlert::SEVERITY_INFO,
                PatientAlert::SEVERITY_WARNING,
                PatientAlert::SEVERITY_CRITICAL,
            ]),
            'message' => fake()->randomElement(['Missed appointment', 'High BP last visit', 'Pending lab review']),
            'is_active' => true,
        ];
    }
}

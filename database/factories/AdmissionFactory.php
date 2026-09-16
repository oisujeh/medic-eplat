<?php

namespace Database\Factories;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_number' => 'ADM/'.now()->year.'/'.Str::upper(Str::random(6)),
            'patient_id' => Patient::factory(),
            'status' => AdmissionStatus::Pending,
            'admitting_diagnosis' => fake()->randomElement(['Severe malaria', 'Pneumonia', 'Diabetic ketoacidosis', 'Acute appendicitis']),
            'reason' => null,
            'requested_by' => User::factory(),
        ];
    }
}

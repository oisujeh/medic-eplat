<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Support\NigeriaLocations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(['M', 'F']);
        $state = fake()->randomElement(NigeriaLocations::states());
        $coverage = fake()->randomElement(['private', 'hmo']);

        return [
            'file_number' => 'MEP/'.now()->year.'/'.fake()->unique()->numerify('######'),
            'title' => $sex === 'F' ? fake()->randomElement(['Mrs', 'Miss', 'Ms']) : 'Mr',
            'surname' => fake()->lastName(),
            'first_name' => fake()->firstName($sex === 'F' ? 'female' : 'male'),
            'other_names' => fake()->optional()->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'sex' => $sex,
            'marital_status' => fake()->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
            'phone' => fake()->numerify('080########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->streetAddress(),
            'nationality' => 'Nigerian',
            'state' => $state,
            'lga' => NigeriaLocations::lgasFor($state)->random(),
            'next_of_kin_name' => fake()->name(),
            'next_of_kin_relationship' => fake()->randomElement(['Spouse', 'Parent', 'Sibling', 'Child', 'Friend']),
            'next_of_kin_phone' => fake()->numerify('080########'),
            'coverage' => $coverage,
            'hmo_name' => $coverage === 'hmo' ? fake()->randomElement(['Hygeia HMO', 'Reliance HMO', 'NHIS']) : null,
            'hmo_number' => $coverage === 'hmo' ? fake()->bothify('HMO-#####') : null,
            'is_transfer' => false,
            'visit_category' => 'Outpatient',
            'outpatient_service' => 'Clinical Consultation & Diagnosis',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\LabOrderStatus;
use App\Enums\Priority;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabOrder>
 */
class LabOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accession_number' => 'LAB/'.now()->year.'/'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'ordered_by' => User::factory(),
            'priority' => Priority::Normal,
            'status' => LabOrderStatus::Ordered,
            'clinical_details' => fake()->optional()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\RequisitionStatus;
use App\Models\StockLocation;
use App\Models\StockRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockRequisition>
 */
class StockRequisitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'REQ/'.now()->year.'/'.fake()->unique()->numerify('######'),
            'from_location_id' => StockLocation::factory()->receiving(),
            'to_location_id' => StockLocation::factory()->dispensary(),
            'status' => RequisitionStatus::Requested,
            'requested_at' => now(),
        ];
    }
}

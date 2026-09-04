<?php

namespace Database\Factories;

use App\Enums\ClaimStatus;
use App\Models\Bill;
use App\Models\Claim;
use App\Models\Patient;
use App\Models\Payer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'claim_number' => 'CLM/'.now()->year.'/'.Str::upper(Str::random(6)),
            'patient_id' => Patient::factory(),
            'payer_id' => Payer::factory(),
            'bill_id' => fn (array $attributes) => Bill::factory()->create(['patient_id' => $attributes['patient_id']])->id,
            'status' => ClaimStatus::Draft,
            'service_date' => now()->toDateString(),
            'gross_amount' => 0,
            'discount_amount' => 0,
            'copay_amount' => 0,
            'payer_amount' => 0,
            'paid_amount' => 0,
        ];
    }
}

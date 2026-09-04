<?php

namespace Database\Factories;

use App\Enums\PayerType;
use App\Models\Payer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payer>
 */
class PayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' HMO',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'type' => PayerType::Hmo,
            'discount_percent' => 0,
            'drug_copay_percent' => 0,
            'is_active' => true,
        ];
    }

    /**
     * The national scheme with its 10% drug co-payment.
     */
    public function nhia(): static
    {
        return $this->state(fn () => [
            'name' => 'National Health Insurance Authority',
            'code' => 'NHIA',
            'type' => PayerType::Nhia,
            'drug_copay_percent' => 10,
        ]);
    }
}

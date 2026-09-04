<?php

namespace Database\Seeders;

use App\Enums\ServiceCategory;
use App\Models\ServiceCharge;
use Illuminate\Database\Seeder;

class ServiceChargesSeeder extends Seeder
{
    /**
     * The starter fee schedule. Editable in-app afterwards.
     * [code, name, category, unit, price]
     *
     * @var array<int, array{0:string,1:string,2:ServiceCategory,3:?string,4:float}>
     */
    protected array $services = [
        ['CONSULTATION', 'General Consultation', ServiceCategory::Consultation, 'per visit', 2000],
        ['CONSULT-FU', 'Follow-up Consultation', ServiceCategory::Consultation, 'per visit', 1000],
        ['CONSULT-SPEC', 'Specialist Consultation', ServiceCategory::Consultation, 'per visit', 5000],
        ['ADMISSION', 'Admission Fee', ServiceCategory::Admission, 'per admission', 5000],
        ['BED-GEN', 'Bed / Room — General Ward', ServiceCategory::Bed, 'per day', 3000],
        ['BED-AMEN', 'Bed / Room — Amenity Ward', ServiceCategory::Bed, 'per day', 8000],
        ['BED-PRIV', 'Bed / Room — Private Room', ServiceCategory::Bed, 'per day', 15000],
        ['BED-ICU', 'Bed / Room — ICU', ServiceCategory::Bed, 'per day', 25000],
        ['DRESSING', 'Wound Dressing', ServiceCategory::Procedure, 'each', 1500],
        ['SUTURE', 'Suturing / Wound Closure', ServiceCategory::Procedure, 'each', 5000],
        ['NEBULIZE', 'Nebulization', ServiceCategory::Procedure, 'each', 2000],
        ['OXYGEN', 'Oxygen Therapy', ServiceCategory::Procedure, 'per hour', 1000],
        ['CATHETER', 'Urethral Catheterization', ServiceCategory::Procedure, 'each', 3000],
        ['INJECTION', 'Injection Administration', ServiceCategory::Nursing, 'each', 500],
    ];

    /**
     * Seed the fee schedule.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->services as [$code, $name, $category, $unit, $price]) {
            ServiceCharge::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'unit' => $unit,
                    'price' => $price,
                    'is_active' => true,
                    'sort_order' => $sort += 10,
                ],
            );
        }
    }
}

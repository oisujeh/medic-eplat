<?php

namespace Database\Seeders;

use App\Enums\WardType;
use App\Models\ServiceCharge;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardsSeeder extends Seeder
{
    /**
     * Starter wards for a general hospital, each priced from the fee schedule.
     * Beds are only created for a ward that has none, so re-seeding never
     * disturbs a live bed board.
     *
     * [code, name, type, bed charge code, beds]
     *
     * @var array<int, array{0: string, 1: string, 2: WardType, 3: string, 4: int}>
     */
    protected array $wards = [
        ['MMW', 'Male Medical Ward', WardType::Male, 'BED-GEN', 12],
        ['FMW', 'Female Medical Ward', WardType::Female, 'BED-GEN', 12],
        ['PAED', 'Paediatric Ward', WardType::Paediatric, 'BED-GEN', 10],
        ['MAT', 'Maternity Ward', WardType::Maternity, 'BED-GEN', 10],
        ['SURG', 'Surgical Ward', WardType::Surgical, 'BED-GEN', 10],
        ['ICU', 'Intensive Care Unit', WardType::Icu, 'BED-ICU', 4],
        ['AMEN', 'Amenity Ward', WardType::Amenity, 'BED-AMEN', 6],
        ['PRIV', 'Private Rooms', WardType::Amenity, 'BED-PRIV', 4],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $charges = ServiceCharge::query()->pluck('id', 'code');

        foreach ($this->wards as $index => [$code, $name, $type, $chargeCode, $beds]) {
            $ward = Ward::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'bed_service_charge_id' => $charges->get($chargeCode),
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );

            if ($ward->beds()->doesntExist()) {
                $ward->addBeds($beds);
            }
        }
    }
}

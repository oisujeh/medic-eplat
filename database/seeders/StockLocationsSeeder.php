<?php

namespace Database\Seeders;

use App\Enums\StockLocationKind;
use App\Models\ServicePoint;
use App\Models\StockLocation;
use Illuminate\Database\Seeder;

/**
 * The places stock is kept on a fresh installation: a main store that
 * receives deliveries, and a dispensary supplying the pharmacy service point.
 */
class StockLocationsSeeder extends Seeder
{
    public function run(): void
    {
        StockLocation::updateOrCreate(
            ['slug' => StockLocation::MAIN_STORE],
            [
                'name' => 'Main store',
                'kind' => StockLocationKind::Store,
                'receives_deliveries' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        StockLocation::updateOrCreate(
            ['slug' => StockLocation::DISPENSARY],
            [
                'name' => 'Dispensary',
                'kind' => StockLocationKind::Dispensary,
                'service_point_id' => ServicePoint::query()->where('slug', 'pharmacy')->value('id'),
                'receives_deliveries' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
    }
}

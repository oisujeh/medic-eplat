<?php

namespace App\Services;

use App\Enums\StockLocationKind;
use App\Models\ServicePoint;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers "which store?" for the rest of the application: where deliveries
 * land, where a service point draws its stock from, and what exists.
 *
 * A facility that has never set up locations still has a main store, so
 * receiving and issuing work from the first day.
 */
class StockLocations
{
    /**
     * Every active location, in display order.
     *
     * @return Collection<int, StockLocation>
     */
    public function all(): Collection
    {
        return StockLocation::query()->active()->get();
    }

    /**
     * The location supplier deliveries are received into.
     */
    public function receiving(): StockLocation
    {
        return StockLocation::query()->active()->where('receives_deliveries', true)->first()
            ?? StockLocation::query()->active()->where('kind', StockLocationKind::Store->value)->first()
            ?? $this->mainStore();
    }

    /**
     * The location patients are dispensed from when no service point says
     * otherwise: the first dispensary, else wherever deliveries land.
     */
    public function dispensary(): StockLocation
    {
        return StockLocation::query()->active()->where('kind', StockLocationKind::Dispensary->value)->first()
            ?? $this->receiving();
    }

    /**
     * The location a service point draws its stock from.
     */
    public function forServicePoint(?ServicePoint $servicePoint): StockLocation
    {
        if ($servicePoint === null) {
            return $this->dispensary();
        }

        return StockLocation::query()->active()->where('service_point_id', $servicePoint->id)->first()
            ?? $this->dispensary();
    }

    /**
     * The main store, created on first use so a facility always has one.
     */
    public function mainStore(): StockLocation
    {
        return StockLocation::query()->firstOrCreate(
            ['slug' => StockLocation::MAIN_STORE],
            [
                'name' => 'Main store',
                'kind' => StockLocationKind::Store,
                'receives_deliveries' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }
}

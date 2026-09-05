<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\GlobalProperty;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\ReorderSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reorder planning from what the facility actually uses.
 *
 * Average monthly consumption (AMC) is what left the store to patients and
 * wards over a trailing window; transfers between locations and count
 * corrections are not consumption. From the AMC and the facility's lead
 * time and cover targets come a suggested reorder level and order quantity
 * for every item.
 */
class StockReorder
{
    public const KEY_LEAD_TIME_DAYS = 'inventory.lead_time_days';

    public const KEY_MIN_COVER_MONTHS = 'inventory.min_cover_months';

    public const KEY_MAX_COVER_MONTHS = 'inventory.max_cover_months';

    public const KEY_WINDOW_DAYS = 'inventory.consumption_window_days';

    private const CACHE_KEY = 'inventory.reorder_settings';

    /**
     * @var array<string, array{default: int|float, description: string}>
     */
    private const DEFAULTS = [
        self::KEY_LEAD_TIME_DAYS => ['default' => 14, 'description' => 'Days between placing an order and stock arriving.'],
        self::KEY_MIN_COVER_MONTHS => ['default' => 1, 'description' => 'Months of consumption to hold as a buffer beyond the lead time.'],
        self::KEY_MAX_COVER_MONTHS => ['default' => 3, 'description' => 'Months of consumption an order should bring stock up to.'],
        self::KEY_WINDOW_DAYS => ['default' => 90, 'description' => 'Trailing days of issues used to average monthly consumption.'],
    ];

    /**
     * @return array{lead_time_days: int, min_cover_months: float, max_cover_months: float, consumption_window_days: int}
     */
    public function settings(): array
    {
        /** @var array{lead_time_days: int, min_cover_months: float, max_cover_months: float, consumption_window_days: int} */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $values = GlobalProperty::query()
                ->whereIn('key', array_keys(self::DEFAULTS))
                ->pluck('value', 'key');

            $get = fn (string $key) => $values->get($key) ?? self::DEFAULTS[$key]['default'];

            return [
                'lead_time_days' => max(0, (int) $get(self::KEY_LEAD_TIME_DAYS)),
                'min_cover_months' => max(0, (float) $get(self::KEY_MIN_COVER_MONTHS)),
                'max_cover_months' => max(0, (float) $get(self::KEY_MAX_COVER_MONTHS)),
                'consumption_window_days' => max(1, (int) $get(self::KEY_WINDOW_DAYS)),
            ];
        });
    }

    /**
     * @param  array{lead_time_days: int, min_cover_months: float, max_cover_months: float, consumption_window_days: int}  $settings
     */
    public function save(array $settings): void
    {
        GlobalProperty::put(self::KEY_LEAD_TIME_DAYS, (string) $settings['lead_time_days'], self::DEFAULTS[self::KEY_LEAD_TIME_DAYS]['description']);
        GlobalProperty::put(self::KEY_MIN_COVER_MONTHS, (string) $settings['min_cover_months'], self::DEFAULTS[self::KEY_MIN_COVER_MONTHS]['description']);
        GlobalProperty::put(self::KEY_MAX_COVER_MONTHS, (string) $settings['max_cover_months'], self::DEFAULTS[self::KEY_MAX_COVER_MONTHS]['description']);
        GlobalProperty::put(self::KEY_WINDOW_DAYS, (string) $settings['consumption_window_days'], self::DEFAULTS[self::KEY_WINDOW_DAYS]['description']);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Units issued per item over the consumption window.
     *
     * @return Collection<int, int> item id => units issued
     */
    public function consumption(): Collection
    {
        $window = $this->settings()['consumption_window_days'];

        return StockMovement::query()
            ->where('type', StockMovementType::Issue->value)
            ->where('created_at', '>=', now()->subDays($window)->startOfDay())
            ->selectRaw('inventory_item_id, sum(-quantity_change) as issued')
            ->groupBy('inventory_item_id')
            ->pluck('issued', 'inventory_item_id')
            ->map(fn ($issued) => (int) $issued);
    }

    /**
     * Average monthly consumption per item over the window.
     *
     * @return Collection<int, float> item id => units per month
     */
    public function averageMonthlyConsumption(): Collection
    {
        $months = $this->settings()['consumption_window_days'] / 30;

        return $this->consumption()->map(fn (int $issued) => round($issued / $months, 1));
    }

    /**
     * A reorder suggestion for every active item, items that need ordering
     * first, then the least cover first.
     *
     * @return Collection<int, ReorderSuggestion>
     */
    public function suggestions(): Collection
    {
        $settings = $this->settings();
        $amc = $this->averageMonthlyConsumption();
        $reorderMonths = $settings['lead_time_days'] / 30 + $settings['min_cover_months'];

        return InventoryItem::query()->active()->get()
            ->map(function (InventoryItem $item) use ($amc, $settings, $reorderMonths): ReorderSuggestion {
                $monthly = (float) ($amc->get($item->id) ?? 0);
                $onHand = $item->quantity_on_hand;
                $level = (int) ceil($monthly * $reorderMonths);
                $target = (int) ceil($monthly * $settings['max_cover_months']);

                return new ReorderSuggestion(
                    item: $item,
                    averageMonthlyConsumption: $monthly,
                    onHand: $onHand,
                    monthsOfCover: $monthly > 0 ? round($onHand / $monthly, 1) : null,
                    suggestedReorderLevel: $level,
                    suggestedOrderQuantity: $monthly > 0 ? max(0, $target - $onHand) : 0,
                    needsOrder: $monthly > 0 && $onHand <= $level,
                );
            })
            ->sortBy(fn (ReorderSuggestion $s) => [$s->needsOrder ? 0 : 1, $s->monthsOfCover ?? PHP_FLOAT_MAX])
            ->values()
            ->toBase();
    }

    /**
     * Set every consuming item's reorder level to its suggestion. Items with
     * no consumption in the window keep the level they have.
     *
     * @return int items updated
     */
    public function applySuggestedLevels(): int
    {
        $updated = 0;

        foreach ($this->suggestions() as $suggestion) {
            if (! $suggestion->isConsumed() || $suggestion->item->reorder_level === $suggestion->suggestedReorderLevel) {
                continue;
            }

            $suggestion->item->update(['reorder_level' => $suggestion->suggestedReorderLevel]);
            $updated++;
        }

        return $updated;
    }
}

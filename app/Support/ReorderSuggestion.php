<?php

namespace App\Support;

use App\Models\InventoryItem;

/**
 * What the reorder planner says about one item: how fast it is used, how
 * long the stock will last, and what to do about it.
 */
final readonly class ReorderSuggestion
{
    public function __construct(
        public InventoryItem $item,
        /** Units used per month over the consumption window. */
        public float $averageMonthlyConsumption,
        public int $onHand,
        /** Months the stock on hand will last; null when nothing is being used. */
        public ?float $monthsOfCover,
        public int $suggestedReorderLevel,
        public int $suggestedOrderQuantity,
        public bool $needsOrder,
    ) {}

    /**
     * Whether the item was used at all in the window.
     */
    public function isConsumed(): bool
    {
        return $this->averageMonthlyConsumption > 0;
    }
}

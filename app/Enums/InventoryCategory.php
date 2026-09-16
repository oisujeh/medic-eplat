<?php

namespace App\Enums;

/**
 * The broad kind of stock item held in the store.
 */
enum InventoryCategory: string
{
    case Drug = 'drug';
    case Consumable = 'consumable';
    case Reagent = 'reagent';
    case Other = 'other';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Drug => 'Drug',
            self::Consumable => 'Consumable',
            self::Reagent => 'Reagent',
            self::Other => 'Other',
        };
    }
}

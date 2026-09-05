<?php

namespace App\Enums;

/**
 * What a stock location is for.
 */
enum StockLocationKind: string
{
    case Store = 'store';           // bulk store; receives supplier deliveries
    case Dispensary = 'dispensary'; // issues to patients
    case Ward = 'ward';             // a ward's own cupboard

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store',
            self::Dispensary => 'Dispensary',
            self::Ward => 'Ward store',
        };
    }
}

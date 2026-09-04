<?php

namespace App\Enums;

/**
 * Why stock quantity changed. Every movement writes a signed delta to the
 * ledger so on-hand quantity is always reconstructable.
 */
enum StockMovementType: string
{
    case Receipt = 'receipt';       // stock received into a batch
    case Issue = 'issue';           // stock issued out (e.g. dispensed)
    case Adjustment = 'adjustment'; // manual correction (count, damage, loss)
    case Return = 'return';         // stock returned into store

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Issue => 'Issue',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
        };
    }
}

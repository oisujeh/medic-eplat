<?php

namespace App\Enums;

/**
 * Why stock quantity changed. Every movement writes a signed delta to the
 * ledger so on-hand quantity is always reconstructable.
 */
enum StockMovementType: string
{
    case Receipt = 'receipt';           // stock received into a batch
    case Issue = 'issue';               // stock issued out (e.g. dispensed)
    case Adjustment = 'adjustment';     // manual correction (count, damage, loss)
    case Return = 'return';             // stock returned into store
    case TransferOut = 'transfer_out';  // left a location on a requisition
    case TransferIn = 'transfer_in';    // arrived at a location on a requisition
    case Disposal = 'disposal';         // expired or damaged stock destroyed
    case Count = 'count';               // variance found at a physical count

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
            self::TransferOut => 'Transfer out',
            self::TransferIn => 'Transfer in',
            self::Disposal => 'Disposal',
            self::Count => 'Physical count',
        };
    }

    /**
     * Whether the movement is consumption: stock that left the facility's
     * hands, as opposed to a correction or a move between locations.
     */
    public function isConsumption(): bool
    {
        return $this === self::Issue;
    }
}

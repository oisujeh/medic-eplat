<?php

namespace App\Enums;

/**
 * The settlement state of a patient's running bill for a visit.
 */
enum BillStatus: string
{
    case Open = 'open';                   // accruing charges, unpaid
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::PartiallyPaid => 'Part-paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the bill is still accepting charges.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid], true);
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::PartiallyPaid => 'blue',
            self::Paid => 'green',
            self::Cancelled => 'muted',
        };
    }
}

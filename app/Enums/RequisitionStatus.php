<?php

namespace App\Enums;

/**
 * The life of a stock requisition.
 *
 * requested → approved → issued → received
 *           ↘ cancelled (until issued)
 */
enum RequisitionStatus: string
{
    case Requested = 'requested';   // raised by the receiving location
    case Approved = 'approved';     // store agreed the quantities to supply
    case Issued = 'issued';         // stock moved; awaiting the receiver's count
    case Received = 'received';     // receiver confirmed what arrived
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Issued => 'Issued',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved => 'blue',
            self::Issued => 'violet',
            self::Received => 'green',
            self::Cancelled => 'muted',
        };
    }

    /**
     * Still moving through the store.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Issued], true);
    }

    /**
     * Whether the requisition can still be withdrawn.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Requested, self::Approved], true);
    }
}

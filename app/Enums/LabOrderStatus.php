<?php

namespace App\Enums;

/**
 * The lifecycle of a laboratory requisition, from order to released result.
 *
 * ordered → collected → in_progress → completed
 *                    ↘ cancelled (from any active state)
 */
enum LabOrderStatus: string
{
    case Ordered = 'ordered';           // requested; awaiting specimen collection
    case Collected = 'collected';       // specimen taken; en route to the bench
    case InProgress = 'in_progress';    // received in the lab; being analysed / resulted
    case Completed = 'completed';       // results verified and released to the chart
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Awaiting collection',
            self::Collected => 'Specimen collected',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the order is still being worked (not released or cancelled).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Ordered, self::Collected, self::InProgress], true);
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Ordered => 'amber',
            self::Collected => 'blue',
            self::InProgress => 'violet',
            self::Completed => 'green',
            self::Cancelled => 'muted',
        };
    }
}

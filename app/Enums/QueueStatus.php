<?php

namespace App\Enums;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case InService = 'in_service';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::InService => 'In service',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the entry is still active in a queue (not finished).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::InService], true);
    }
}

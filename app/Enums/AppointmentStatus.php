<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::CheckedIn => 'Checked in',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
        };
    }

    /**
     * Whether the appointment still occupies its slot (for conflict checks).
     */
    public function occupiesSlot(): bool
    {
        return in_array($this, [self::Scheduled, self::CheckedIn], true);
    }

    /**
     * Whether the appointment is still open to be checked in / rescheduled / cancelled.
     */
    public function isOpen(): bool
    {
        return $this === self::Scheduled;
    }
}

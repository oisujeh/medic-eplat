<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Booked = 'booked';
    case WalkIn = 'walk_in';
    case FollowUp = 'follow_up';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::WalkIn => 'Walk-in',
            self::FollowUp => 'Follow-up',
        };
    }
}

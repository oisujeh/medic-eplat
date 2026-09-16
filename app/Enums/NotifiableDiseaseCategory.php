<?php

namespace App\Enums;

/**
 * How quickly a priority disease must be reported under IDSR.
 */
enum NotifiableDiseaseCategory: string
{
    case Immediate = 'immediate';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediately notifiable',
            self::Weekly => 'Weekly reportable',
        };
    }

    /**
     * What the clinician must do when a case is detected.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Immediate => 'Notify the LGA Disease Surveillance and Notification Officer (DSNO) within 24 hours.',
            self::Weekly => 'Reported on the weekly IDSR 002 return. No immediate notification is required.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Immediate => 'red',
            self::Weekly => 'amber',
        };
    }
}

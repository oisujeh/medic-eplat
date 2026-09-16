<?php

namespace App\Enums;

/**
 * Grouping for billable services in the fee schedule.
 */
enum ServiceCategory: string
{
    case Consultation = 'consultation';
    case Admission = 'admission';
    case Bed = 'bed';
    case Procedure = 'procedure';
    case Nursing = 'nursing';
    case Other = 'other';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::Admission => 'Admission',
            self::Bed => 'Bed / Room',
            self::Procedure => 'Procedure',
            self::Nursing => 'Nursing',
            self::Other => 'Other',
        };
    }
}

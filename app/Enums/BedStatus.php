<?php

namespace App\Enums;

/**
 * Whether a bed can take a patient right now.
 */
enum BedStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case OutOfService = 'out_of_service';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::OutOfService => 'Out of service',
        };
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Occupied => 'blue',
            self::OutOfService => 'muted',
        };
    }
}

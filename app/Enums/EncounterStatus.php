<?php

namespace App\Enums;

enum EncounterStatus: string
{
    case InProgress = 'in_progress';
    case Signed = 'signed';
    case Cancelled = 'cancelled';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Signed => 'Signed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the encounter can still be documented.
     */
    public function isOpen(): bool
    {
        return $this === self::InProgress;
    }
}

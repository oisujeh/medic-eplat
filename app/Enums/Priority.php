<?php

namespace App\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case Emergency = 'emergency';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgent => 'Urgent',
            self::Emergency => 'Emergency',
        };
    }

    /**
     * Sort weight — higher priority is called first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Emergency => 3,
            self::Urgent => 2,
            self::Normal => 1,
        };
    }
}

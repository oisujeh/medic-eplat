<?php

namespace App\Enums;

/**
 * Where a pregnancy episode stands.
 *
 * active → delivered
 *        ↘ lost (miscarriage, abortion, ectopic)
 */
enum PregnancyStatus: string
{
    case Active = 'active';
    case Delivered = 'delivered';
    case Lost = 'lost';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Antenatal',
            self::Delivered => 'Delivered',
            self::Lost => 'Pregnancy lost',
        };
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Delivered => 'green',
            self::Lost => 'muted',
        };
    }
}

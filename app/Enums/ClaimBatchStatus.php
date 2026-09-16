<?php

namespace App\Enums;

/**
 * Whether a claims schedule is still collecting claims or has been sent.
 */
enum ClaimBatchStatus: string
{
    case Open = 'open';
    case Submitted = 'submitted';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Submitted => 'Submitted',
        };
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Submitted => 'green',
        };
    }
}

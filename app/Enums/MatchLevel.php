<?php

namespace App\Enums;

/**
 * What a match score means, against the configured thresholds.
 */
enum MatchLevel: string
{
    case Auto = 'auto';       // near-certain: the same person
    case Review = 'review';   // probable: queued for a person to decide
    case Hint = 'hint';       // worth showing the registrar, not queued
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Almost certainly the same person',
            self::Review => 'Probably the same person',
            self::Hint => 'Possibly the same person',
            self::None => 'Unlikely',
        };
    }

    public function isQueued(): bool
    {
        return in_array($this, [self::Auto, self::Review], true);
    }

    public function isShown(): bool
    {
        return $this !== self::None;
    }
}

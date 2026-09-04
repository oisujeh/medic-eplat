<?php

namespace App\Enums;

/**
 * Whether a baby was born alive.
 */
enum BirthOutcome: string
{
    case Live = 'live';
    case StillbirthFresh = 'stillbirth_fresh';
    case StillbirthMacerated = 'stillbirth_macerated';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live birth',
            self::StillbirthFresh => 'Stillbirth (fresh)',
            self::StillbirthMacerated => 'Stillbirth (macerated)',
        };
    }

    public function isLive(): bool
    {
        return $this === self::Live;
    }

    /**
     * Options for a select control.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $o) => ['value' => $o->value, 'label' => $o->label()], self::cases());
    }
}

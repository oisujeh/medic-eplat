<?php

namespace App\Enums;

/**
 * The mother's condition after delivery.
 */
enum MaternalOutcome: string
{
    case Well = 'well';
    case Referred = 'referred';
    case Deceased = 'deceased';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Well => 'Well',
            self::Referred => 'Referred / transferred out',
            self::Deceased => 'Maternal death',
        };
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

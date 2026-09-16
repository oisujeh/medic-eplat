<?php

namespace App\Enums;

/**
 * The patient's outcome for a surveillance case, as reported on the line list.
 */
enum CaseOutcome: string
{
    case Unknown = 'unknown';
    case Alive = 'alive';
    case Dead = 'dead';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not yet known',
            self::Alive => 'Alive',
            self::Dead => 'Dead',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}

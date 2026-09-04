<?php

namespace App\Enums;

enum EncounterOutcome: string
{
    case Home = 'home';
    case Admit = 'admit';
    case Transfer = 'transfer';
    case Referred = 'referred';
    case Deceased = 'deceased';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Admit => 'Admit',
            self::Transfer => 'Transfer',
            self::Referred => 'Referred',
            self::Deceased => 'Deceased',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $o) => ['value' => $o->value, 'label' => $o->label()], self::cases());
    }
}

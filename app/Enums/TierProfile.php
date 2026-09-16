<?php

namespace App\Enums;

/**
 * The configuration bundle the facility runs (PRD 2.3): the same software
 * with a different set of modules, forms and reports switched on.
 */
enum TierProfile: string
{
    case Phc = 'phc';
    case Secondary = 'secondary';
    case Tertiary = 'tertiary';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Phc => 'PHC',
            self::Secondary => 'Secondary',
            self::Tertiary => 'Tertiary',
            self::Private => 'Private',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}

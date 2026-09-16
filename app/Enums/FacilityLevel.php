<?php

namespace App\Enums;

/**
 * The level of care the facility is registered for (PRD ADM-01).
 */
enum FacilityLevel: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Tertiary = 'tertiary';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary (PHC)',
            self::Secondary => 'Secondary (general hospital)',
            self::Tertiary => 'Tertiary (teaching or specialist)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $l) => ['value' => $l->value, 'label' => $l->label()], self::cases());
    }
}

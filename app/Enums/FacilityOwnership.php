<?php

namespace App\Enums;

/**
 * Who owns the facility (PRD ADM-01, INS-02).
 */
enum FacilityOwnership: string
{
    case Public = 'public';
    case Private = 'private';
    case FaithBased = 'faith_based';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::FaithBased => 'Faith-based',
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

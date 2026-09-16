<?php

namespace App\Enums;

/**
 * The kind of ward, used for grouping and to suggest sensible defaults.
 */
enum WardType: string
{
    case General = 'general';
    case Male = 'male';
    case Female = 'female';
    case Paediatric = 'paediatric';
    case Maternity = 'maternity';
    case Surgical = 'surgical';
    case Emergency = 'emergency';
    case Icu = 'icu';
    case Isolation = 'isolation';
    case Amenity = 'amenity';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Male => 'Male',
            self::Female => 'Female',
            self::Paediatric => 'Paediatric',
            self::Maternity => 'Maternity',
            self::Surgical => 'Surgical',
            self::Emergency => 'Emergency / Observation',
            self::Icu => 'Intensive care',
            self::Isolation => 'Isolation',
            self::Amenity => 'Amenity / Private',
        };
    }

    /**
     * Options for a select control.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}

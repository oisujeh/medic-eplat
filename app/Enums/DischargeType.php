<?php

namespace App\Enums;

/**
 * How an inpatient episode ended.
 */
enum DischargeType: string
{
    case Home = 'home';
    case Referred = 'referred';
    case Dama = 'dama';             // discharged against medical advice
    case Absconded = 'absconded';
    case Deceased = 'deceased';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Home => 'Discharged home',
            self::Referred => 'Referred / transferred out',
            self::Dama => 'Discharged against medical advice',
            self::Absconded => 'Absconded',
            self::Deceased => 'Deceased',
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

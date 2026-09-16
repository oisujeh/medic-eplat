<?php

namespace App\Enums;

/**
 * The two ways a form is filled (PRD FRM-07): guided at the point of care,
 * one page at a time; or rapid retrospective entry from paper, every field
 * on one page in a fixed tab order.
 */
enum FormEntryMode: string
{
    case Guided = 'guided';
    case Rapid = 'rapid';

    public function label(): string
    {
        return match ($this) {
            self::Guided => 'Guided (point of care)',
            self::Rapid => 'Rapid (retrospective entry)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}

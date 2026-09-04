<?php

namespace App\Enums;

/**
 * The kind of third-party payer.
 */
enum PayerType: string
{
    case Nhia = 'nhia';           // the national scheme (formerly NHIS)
    case Hmo = 'hmo';             // a private health maintenance organisation
    case Corporate = 'corporate'; // an employer retainer scheme

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nhia => 'NHIA',
            self::Hmo => 'HMO',
            self::Corporate => 'Corporate scheme',
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

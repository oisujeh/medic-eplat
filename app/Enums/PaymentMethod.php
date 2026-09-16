<?php

namespace App\Enums;

/**
 * How a payment against a bill was tendered.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Pos = 'pos';
    case Hmo = 'hmo';
    case Waiver = 'waiver';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Transfer => 'Bank transfer',
            self::Pos => 'POS',
            self::Hmo => 'HMO',
            self::Waiver => 'Waiver',
        };
    }
}

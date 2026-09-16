<?php

namespace App\Enums;

/**
 * Who paid for a batch of stock (PRD STK-02), so programme commodities and
 * revolving-fund stock never mix in a report.
 */
enum FundingSource: string
{
    case Government = 'government';
    case DrugRevolvingFund = 'drf';
    case Pepfar = 'pepfar';
    case GlobalFund = 'global_fund';
    case PrivatePurchase = 'private_purchase';
    case Donation = 'donation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Government => 'Government',
            self::DrugRevolvingFund => 'Drug revolving fund',
            self::Pepfar => 'PEPFAR',
            self::GlobalFund => 'Global Fund',
            self::PrivatePurchase => 'Private purchase',
            self::Donation => 'Donation',
            self::Other => 'Other',
        };
    }

    /**
     * Programme stock is issued free of charge.
     */
    public function isProgramme(): bool
    {
        return in_array($this, [self::Pepfar, self::GlobalFund, self::Donation], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

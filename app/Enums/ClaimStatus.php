<?php

namespace App\Enums;

/**
 * The lifecycle of a claim.
 *
 * draft → submitted → partially_paid → paid
 *                  ↘ rejected
 */
enum ClaimStatus: string
{
    case Draft = 'draft';                   // being prepared; lines still editable
    case Submitted = 'submitted';           // in a batch, awaiting the payer
    case PartiallyPaid = 'partially_paid';  // some remittance received
    case Paid = 'paid';                     // settled by the payer
    case Rejected = 'rejected';             // declined by the payer

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Whether money is still expected from the payer.
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Submitted, self::PartiallyPaid], true);
    }

    /**
     * Whether the claim can still change (authorisation, remittance).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::PartiallyPaid], true);
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Submitted => 'blue',
            self::PartiallyPaid => 'amber',
            self::Paid => 'green',
            self::Rejected => 'red',
        };
    }

    /**
     * Options for a filter control.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

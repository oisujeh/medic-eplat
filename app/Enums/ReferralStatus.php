<?php

namespace App\Enums;

/**
 * Where an outbound referral stands with the receiving facility.
 */
enum ReferralStatus: string
{
    /** Letter issued; nothing heard back yet. */
    case Issued = 'issued';

    /** The receiving facility confirmed it will take the patient. */
    case Accepted = 'accepted';

    /** The patient was seen and feedback received. */
    case Seen = 'seen';

    /** The receiving facility declined, or the patient did not go. */
    case Declined = 'declined';

    /** Withdrawn by the referring facility. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Accepted => 'Accepted',
            self::Seen => 'Patient seen',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Issued => 'amber',
            self::Accepted => 'blue',
            self::Seen => 'green',
            self::Declined => 'red',
            self::Cancelled => 'muted',
        };
    }

    /**
     * Still awaiting the receiving facility.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::Accepted], true);
    }

    /**
     * The statuses this one may move to.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Issued => [self::Accepted, self::Seen, self::Declined, self::Cancelled],
            self::Accepted => [self::Seen, self::Declined, self::Cancelled],
            self::Seen, self::Declined, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

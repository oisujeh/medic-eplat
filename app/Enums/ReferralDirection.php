<?php

namespace App\Enums;

/**
 * Which way a referral travels (PRD REF-01 to REF-03).
 */
enum ReferralDirection: string
{
    /** Sent to another facility with a letter. */
    case Outbound = 'outbound';

    /** Received from another facility, linked to the visit it opened. */
    case Inbound = 'inbound';

    /** Between departments of this facility; creates a queue entry. */
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Referral out',
            self::Inbound => 'Referral in',
            self::Internal => 'Internal referral',
        };
    }
}

<?php

namespace App\Enums;

enum MatchCandidateStatus: string
{
    case Pending = 'pending';
    case Dismissed = 'dismissed';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Dismissed => 'Not the same person',
            self::Merged => 'Merged',
        };
    }
}

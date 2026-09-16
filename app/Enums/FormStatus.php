<?php

namespace App\Enums;

/**
 * Where a form is in its life: drafted, offered to clinicians, or withdrawn.
 */
enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Retired => 'Retired',
        };
    }

    /**
     * Whether clinicians can start new submissions of the form.
     */
    public function isOffered(): bool
    {
        return $this === self::Published;
    }
}

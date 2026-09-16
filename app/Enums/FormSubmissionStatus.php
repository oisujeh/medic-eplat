<?php

namespace App\Enums;

/**
 * The state of one filled form.
 */
enum FormSubmissionStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Completed => 'Completed',
            self::Voided => 'Voided',
        };
    }

    /**
     * Whether answers can still be changed.
     */
    public function isEditable(): bool
    {
        return $this !== self::Voided;
    }
}

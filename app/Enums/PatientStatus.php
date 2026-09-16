<?php

namespace App\Enums;

/**
 * Where a patient stands with the facility (PRD REG-19). A change is dated
 * and audited on the patient record; death is set by the death register.
 */
enum PatientStatus: string
{
    case Active = 'active';
    case TransferredOut = 'transferred_out';
    case Deceased = 'deceased';
    case LostToFollowUp = 'lost_to_follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::TransferredOut => 'Transferred out',
            self::Deceased => 'Deceased',
            self::LostToFollowUp => 'Lost to follow-up',
        };
    }

    /**
     * Whether the patient can still be routed, booked and billed here.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

<?php

namespace App\Enums;

/**
 * The kinds of identifier a patient can carry. The facility file number,
 * phone and HMO number are mirrored from the patient record so every
 * identifier is searchable in one place; the others are held only here.
 */
enum IdentifierType: string
{
    case FileNumber = 'file_number';
    case Nin = 'nin';
    case Phone = 'phone';
    case HmoNumber = 'hmo_number';
    case NationalHealthId = 'national_health_id';
    case PreviousFileNumber = 'previous_file_number';

    public function label(): string
    {
        return match ($this) {
            self::FileNumber => 'File number',
            self::Nin => 'NIN',
            self::Phone => 'Phone',
            self::HmoNumber => 'HMO / enrollee number',
            self::NationalHealthId => 'National health ID',
            self::PreviousFileNumber => 'Previous file number',
        };
    }

    /**
     * Whether one value of this type can belong to only one person.
     */
    public function isUnique(): bool
    {
        return match ($this) {
            self::FileNumber, self::Nin, self::NationalHealthId => true,
            self::Phone, self::HmoNumber, self::PreviousFileNumber => false,
        };
    }

    /**
     * The patient attribute this type mirrors, when it mirrors one.
     */
    public function patientAttribute(): ?string
    {
        return match ($this) {
            self::FileNumber => 'file_number',
            self::Nin => 'nin',
            self::Phone => 'phone',
            self::HmoNumber => 'hmo_number',
            default => null,
        };
    }

    /**
     * Strip the formatting people type so equal identifiers compare equal.
     */
    public function normalise(string $value): string
    {
        $value = trim($value);

        return match ($this) {
            self::Phone => preg_replace('/\D+/', '', $value) ?? $value,
            self::Nin => preg_replace('/\s+/', '', $value) ?? $value,
            default => $value,
        };
    }
}

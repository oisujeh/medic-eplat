<?php

namespace App\Enums;

/**
 * Whether the pharmacy has supplied a prescription. Kept apart from the
 * clinical status: a fully dispensed drug is still being taken, and a
 * stopped drug may never have been collected.
 *
 * pending → partial → dispensed
 *         ↘ external (sourced outside the facility)
 */
enum MedicationFulfilment: string
{
    case Pending = 'pending';       // nothing supplied yet
    case Partial = 'partial';       // some of the prescribed quantity supplied
    case Dispensed = 'dispensed';   // the prescribed quantity supplied in full
    case External = 'external';     // to be obtained outside; not for this pharmacy

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'To dispense',
            self::Partial => 'Partly dispensed',
            self::Dispensed => 'Dispensed',
            self::External => 'Sourced outside',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Partial => 'blue',
            self::Dispensed => 'green',
            self::External => 'muted',
        };
    }

    /**
     * Whether the pharmacy still owes the patient something on this line.
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Pending, self::Partial], true);
    }
}

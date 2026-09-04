<?php

namespace App\Enums;

/**
 * The lifecycle of an inpatient episode.
 *
 * pending → admitted → discharged
 *        ↘ cancelled
 */
enum AdmissionStatus: string
{
    case Pending = 'pending';       // admission ordered; awaiting a bed
    case Admitted = 'admitted';     // on the ward, in a bed
    case Discharged = 'discharged';
    case Cancelled = 'cancelled';   // ordered but never admitted

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting bed',
            self::Admitted => 'Admitted',
            self::Discharged => 'Discharged',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Whether the patient is still an inpatient (or about to become one).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Admitted], true);
    }

    /**
     * A severity-style token the frontend maps to colour.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Admitted => 'blue',
            self::Discharged => 'green',
            self::Cancelled => 'muted',
        };
    }
}

<?php

namespace App\Enums;

enum EncounterType: string
{
    case Consultation = 'consultation';
    case Triage = 'triage';
    case Nursing = 'nursing';
    case WardRound = 'ward_round';
    case Discharge = 'discharge';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::Triage => 'Triage',
            self::Nursing => 'Nursing',
            self::WardRound => 'Ward round',
            self::Discharge => 'Discharge',
        };
    }

    /**
     * The module whose staff author encounters of this type.
     */
    public function module(): string
    {
        return match ($this) {
            self::Consultation => 'clinical',
            self::Triage, self::Nursing => 'nursing',
            self::WardRound, self::Discharge => 'admissions',
        };
    }

    /**
     * Whether encounters of this type are physician consultations — the
     * ones reported as "consultations" and billed a consultation fee.
     */
    public function isConsultation(): bool
    {
        return $this === self::Consultation;
    }

    /**
     * The encounter type opened at a service point, from its governing module
     * and slug.
     */
    public static function forServicePoint(string $moduleSlug, string $slug): self
    {
        return match (true) {
            $moduleSlug === 'clinical' => self::Consultation,
            $slug === 'triage' => self::Triage,
            default => self::Nursing,
        };
    }
}

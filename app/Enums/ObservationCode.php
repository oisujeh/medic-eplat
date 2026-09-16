<?php

namespace App\Enums;

/**
 * The measurements the system records. Each code is the single source for its
 * label, unit, entry step and the sanity bounds used to validate input; the
 * clinical thresholds that flag a reading live in ObservationInterpreter.
 */
enum ObservationCode: string
{
    // Vital signs
    case Temperature = 'temperature';
    case SystolicBp = 'systolic_bp';
    case DiastolicBp = 'diastolic_bp';
    case Pulse = 'pulse';
    case RespiratoryRate = 'respiratory_rate';
    case Spo2 = 'spo2';
    case BloodGlucose = 'blood_glucose';
    case PainScore = 'pain_score';

    // Anthropometrics
    case Weight = 'weight';
    case Height = 'height';
    case Bmi = 'bmi';
    case Muac = 'muac';
    case HeadCircumference = 'head_circumference';

    // Antenatal
    case GestationalAge = 'gestational_age';
    case FundalHeight = 'fundal_height';
    case FetalHeartRate = 'fetal_heart_rate';
    case Presentation = 'presentation';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Temperature => 'Temperature',
            self::SystolicBp => 'Systolic BP',
            self::DiastolicBp => 'Diastolic BP',
            self::Pulse => 'Pulse',
            self::RespiratoryRate => 'Respiratory rate',
            self::Spo2 => 'SpO₂',
            self::BloodGlucose => 'Blood glucose',
            self::PainScore => 'Pain score',
            self::Weight => 'Weight',
            self::Height => 'Height',
            self::Bmi => 'BMI',
            self::Muac => 'MUAC',
            self::HeadCircumference => 'Head circumference',
            self::GestationalAge => 'Gestational age',
            self::FundalHeight => 'Fundal height',
            self::FetalHeartRate => 'Fetal heart rate',
            self::Presentation => 'Presentation',
        };
    }

    /**
     * Short label for chips and tiles.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Temperature => 'Temp',
            self::RespiratoryRate => 'Resp',
            self::HeadCircumference => 'Head circ.',
            self::GestationalAge => 'GA',
            self::FetalHeartRate => 'FHR',
            default => $this->label(),
        };
    }

    /**
     * Unit of measure, null for text and dimensionless readings.
     */
    public function unit(): ?string
    {
        return match ($this) {
            self::Temperature => '°C',
            self::SystolicBp, self::DiastolicBp => 'mmHg',
            self::Pulse, self::FetalHeartRate => 'bpm',
            self::RespiratoryRate => '/min',
            self::Spo2 => '%',
            self::BloodGlucose => 'mmol/L',
            self::PainScore => '/10',
            self::Weight => 'kg',
            self::Height, self::Muac, self::HeadCircumference, self::FundalHeight => 'cm',
            self::Bmi => 'kg/m²',
            self::GestationalAge => 'wk',
            self::Presentation => null,
        };
    }

    /**
     * The panel a code is captured under.
     */
    public function panel(): ObservationPanel
    {
        return match ($this) {
            self::Temperature, self::SystolicBp, self::DiastolicBp, self::Pulse,
            self::RespiratoryRate, self::Spo2, self::BloodGlucose, self::PainScore => ObservationPanel::Vitals,
            self::Weight, self::Height, self::Bmi, self::Muac, self::HeadCircumference => ObservationPanel::Anthropometrics,
            self::GestationalAge, self::FundalHeight, self::FetalHeartRate, self::Presentation => ObservationPanel::Antenatal,
        };
    }

    /**
     * Whether the reading is a free-text / categorical value.
     */
    public function isText(): bool
    {
        return $this === self::Presentation;
    }

    /**
     * Whether the value is computed from other readings rather than entered.
     */
    public function isDerived(): bool
    {
        return $this === self::Bmi;
    }

    /**
     * Entry step for numeric inputs.
     */
    public function step(): float
    {
        return match ($this) {
            self::Temperature, self::BloodGlucose, self::Height, self::Muac,
            self::HeadCircumference, self::FundalHeight, self::Bmi => 0.1,
            self::Weight => 0.01,
            default => 1,
        };
    }

    /**
     * Generous sanity bounds to catch fat-finger entry, not clinical limits.
     *
     * @return array{0: float|int, 1: float|int}|null
     */
    public function bounds(): ?array
    {
        return match ($this) {
            self::Temperature => [25, 45],
            self::SystolicBp => [40, 300],
            self::DiastolicBp => [20, 200],
            self::Pulse => [20, 300],
            self::RespiratoryRate => [5, 90],
            self::Spo2 => [40, 100],
            self::BloodGlucose => [1, 50],
            self::PainScore => [0, 10],
            self::Weight => [0.3, 500],
            self::Height => [15, 260],
            self::Bmi => [5, 120],
            self::Muac => [5, 60],
            self::HeadCircumference => [20, 70],
            self::GestationalAge => [0, 45],
            self::FundalHeight => [0, 60],
            self::FetalHeartRate => [0, 250],
            self::Presentation => null,
        };
    }

    /**
     * Codes entered by hand (everything except derived values).
     *
     * @return array<int, self>
     */
    public static function enterable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => ! $c->isDerived()));
    }

    /**
     * Field definitions for a capture form, grouped by panel.
     *
     * @return array<int, array{value: string, label: string, short_label: string, unit: string|null, step: float, panel: string, text: bool, derived: bool, min: float|int|null, max: float|int|null}>
     */
    public static function definitions(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'short_label' => $c->shortLabel(),
            'unit' => $c->unit(),
            'step' => $c->step(),
            'panel' => $c->panel()->value,
            'text' => $c->isText(),
            'derived' => $c->isDerived(),
            'min' => $c->bounds()[0] ?? null,
            'max' => $c->bounds()[1] ?? null,
        ], self::cases());
    }
}

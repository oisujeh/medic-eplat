<?php

namespace App\Support;

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;

/**
 * Classifies a single measurement against clinical reference ranges and
 * produces a severity level with a short flag label.
 *
 * Adult thresholds apply by default. Pulse and respiratory rate use
 * paediatric bands when the patient's age is known and under 12. MUAC follows
 * the WHO acute-malnutrition cut-offs.
 */
class ObservationInterpreter
{
    /**
     * Interpret a numeric reading.
     *
     * @return array{level: AlertLevel, flag: string}|null null when the reading is unremarkable
     */
    public static function interpret(ObservationCode $code, float $value, ?int $ageYears = null): ?array
    {
        [$level, $flag] = match ($code) {
            ObservationCode::Temperature => [
                match (true) {
                    $value >= 39.5 => AlertLevel::Critical,
                    $value >= 38.0 => AlertLevel::Warning,
                    $value < 32.0 => AlertLevel::Critical,
                    $value < 35.0 => AlertLevel::Warning,
                    default => null,
                },
                $value >= 38.0 ? 'Fever' : 'Hypothermia',
            ],
            ObservationCode::SystolicBp => [
                match (true) {
                    $value >= 180 => AlertLevel::Critical,
                    $value >= 140 => AlertLevel::Warning,
                    $value < 80 => AlertLevel::Critical,
                    $value < 90 => AlertLevel::Warning,
                    default => null,
                },
                $value >= 140 ? 'Hypertension' : 'Hypotension',
            ],
            ObservationCode::DiastolicBp => [
                match (true) {
                    $value >= 120 => AlertLevel::Critical,
                    $value >= 90 => AlertLevel::Warning,
                    $value < 60 => AlertLevel::Warning,
                    default => null,
                },
                $value >= 90 ? 'Hypertension' : 'Low diastolic',
            ],
            ObservationCode::Pulse => self::pulse($value, $ageYears),
            ObservationCode::RespiratoryRate => self::respiratoryRate($value, $ageYears),
            ObservationCode::Spo2 => [
                match (true) {
                    $value < 90 => AlertLevel::Critical,
                    $value < 94 => AlertLevel::Warning,
                    default => null,
                },
                'Low SpO₂',
            ],
            ObservationCode::BloodGlucose => [
                match (true) {
                    $value < 3.0 => AlertLevel::Critical,
                    $value < 3.9 => AlertLevel::Warning,
                    $value >= 20 => AlertLevel::Critical,
                    $value >= 11.1 => AlertLevel::Warning,
                    default => null,
                },
                $value < 3.9 ? 'Hypoglycaemia' : 'Hyperglycaemia',
            ],
            ObservationCode::PainScore => [
                $value >= 7 ? AlertLevel::Warning : null,
                'Severe pain',
            ],
            ObservationCode::Bmi => [
                match (true) {
                    $value < 16 => AlertLevel::Critical,
                    $value < 18.5 => AlertLevel::Warning,
                    $value >= 40 => AlertLevel::Critical,
                    $value >= 30 => AlertLevel::Warning,
                    default => null,
                },
                $value < 18.5 ? 'Underweight' : 'Obese',
            ],
            ObservationCode::Muac => [
                match (true) {
                    $value < 11.5 => AlertLevel::Critical,
                    $value < 12.5 => AlertLevel::Warning,
                    default => null,
                },
                $value < 11.5 ? 'Severe acute malnutrition' : 'Moderate acute malnutrition',
            ],
            ObservationCode::FetalHeartRate => [
                match (true) {
                    $value <= 0 => null,
                    $value < 100 || $value > 180 => AlertLevel::Critical,
                    $value < 110 || $value > 160 => AlertLevel::Warning,
                    default => null,
                },
                $value < 110 ? 'Fetal bradycardia' : 'Fetal tachycardia',
            ],
            default => [null, ''],
        };

        return $level === null ? null : ['level' => $level, 'flag' => $flag];
    }

    /**
     * Body Mass Index (kg/m²) rounded to one decimal, or null when incomplete.
     */
    public static function bmi(?float $weightKg, ?float $heightCm): ?float
    {
        if (! $weightKg || ! $heightCm || $heightCm <= 0) {
            return null;
        }

        $heightM = $heightCm / 100;

        return round($weightKg / ($heightM ** 2), 1);
    }

    /**
     * @return array{0: AlertLevel|null, 1: string}
     */
    private static function pulse(float $value, ?int $ageYears): array
    {
        [$lowCritical, $low, $high, $highCritical] = match (true) {
            $ageYears === null || $ageYears >= 12 => [40, 60, 100, 130],
            $ageYears < 1 => [80, 100, 160, 180],
            $ageYears < 5 => [60, 80, 140, 160],
            default => [50, 70, 120, 140],
        };

        return [
            match (true) {
                $value >= $highCritical => AlertLevel::Critical,
                $value > $high => AlertLevel::Warning,
                $value < $lowCritical => AlertLevel::Critical,
                $value < $low => AlertLevel::Warning,
                default => null,
            },
            $value > $high ? 'Tachycardia' : 'Bradycardia',
        ];
    }

    /**
     * @return array{0: AlertLevel|null, 1: string}
     */
    private static function respiratoryRate(float $value, ?int $ageYears): array
    {
        [$lowCritical, $low, $high, $highCritical] = match (true) {
            $ageYears === null || $ageYears >= 12 => [8, 12, 20, 30],
            $ageYears < 1 => [20, 30, 60, 70],
            $ageYears < 5 => [15, 20, 40, 50],
            default => [10, 15, 30, 40],
        };

        return [
            match (true) {
                $value >= $highCritical => AlertLevel::Critical,
                $value > $high => AlertLevel::Warning,
                $value < $lowCritical => AlertLevel::Critical,
                $value < $low => AlertLevel::Warning,
                default => null,
            },
            $value > $high ? 'Tachypnoea' : 'Bradypnoea',
        ];
    }
}

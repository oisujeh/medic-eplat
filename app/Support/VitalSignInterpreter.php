<?php

namespace App\Support;

use App\Models\VitalSign;

/**
 * Classifies a set of vitals against clinical reference ranges and produces
 * per-metric flags with a severity level.
 *
 * NOTE: thresholds are adult defaults. Pulse, respiratory rate and BMI in
 * particular differ for children; age-adjusted ranges are a future refinement.
 * MUAC thresholds follow WHO acute-malnutrition cut-offs.
 */
class VitalSignInterpreter
{
    /**
     * Interpret a reading.
     *
     * @return array{level: string, flags: array<int, array{metric: string, level: string, label: string}>}
     */
    public static function interpret(VitalSign $v): array
    {
        $flags = [];

        $add = function (string $metric, ?string $level, string $label) use (&$flags): void {
            if ($level !== null) {
                $flags[] = ['metric' => $metric, 'level' => $level, 'label' => $label];
            }
        };

        // Temperature (°C)
        if ($v->temperature_c !== null) {
            $t = (float) $v->temperature_c;
            $add('temperature_c', match (true) {
                $t >= 39.5 => 'critical',
                $t >= 38.0 => 'warning',
                $t < 32.0 => 'critical',
                $t < 35.0 => 'warning',
                default => null,
            }, $t >= 38.0 ? 'Fever' : 'Hypothermia');
        }

        // Blood pressure (mmHg)
        if ($v->systolic_bp !== null) {
            $s = (int) $v->systolic_bp;
            $add('systolic_bp', match (true) {
                $s >= 180 => 'critical',
                $s >= 140 => 'warning',
                $s < 80 => 'critical',
                $s < 90 => 'warning',
                default => null,
            }, $s >= 140 ? 'Hypertension' : 'Hypotension');
        }
        if ($v->diastolic_bp !== null) {
            $d = (int) $v->diastolic_bp;
            $add('diastolic_bp', match (true) {
                $d >= 120 => 'critical',
                $d >= 90 => 'warning',
                $d < 60 => 'warning',
                default => null,
            }, $d >= 90 ? 'Hypertension' : 'Low diastolic');
        }

        // Pulse (bpm)
        if ($v->pulse_bpm !== null) {
            $p = (int) $v->pulse_bpm;
            $add('pulse_bpm', match (true) {
                $p >= 130 => 'critical',
                $p > 100 => 'warning',
                $p < 40 => 'critical',
                $p < 60 => 'warning',
                default => null,
            }, $p > 100 ? 'Tachycardia' : 'Bradycardia');
        }

        // Respiratory rate (breaths/min)
        if ($v->respiratory_rate !== null) {
            $r = (int) $v->respiratory_rate;
            $add('respiratory_rate', match (true) {
                $r >= 30 => 'critical',
                $r > 20 => 'warning',
                $r < 8 => 'critical',
                $r < 12 => 'warning',
                default => null,
            }, $r > 20 ? 'Tachypnoea' : 'Bradypnoea');
        }

        // Oxygen saturation (%)
        if ($v->spo2 !== null) {
            $o = (int) $v->spo2;
            $add('spo2', match (true) {
                $o < 90 => 'critical',
                $o < 94 => 'warning',
                default => null,
            }, 'Low SpO₂');
        }

        // Blood glucose (mmol/L)
        if ($v->blood_glucose !== null) {
            $g = (float) $v->blood_glucose;
            $add('blood_glucose', match (true) {
                $g < 3.0 => 'critical',
                $g < 3.9 => 'warning',
                $g >= 20 => 'critical',
                $g >= 11.1 => 'warning',
                default => null,
            }, $g < 3.9 ? 'Hypoglycaemia' : 'Hyperglycaemia');
        }

        // Pain (0–10)
        if ($v->pain_score !== null) {
            $add('pain_score', $v->pain_score >= 7 ? 'warning' : null, 'Severe pain');
        }

        // BMI (kg/m²)
        if ($v->bmi !== null) {
            $b = (float) $v->bmi;
            $add('bmi', match (true) {
                $b < 16 => 'critical',
                $b < 18.5 => 'warning',
                $b >= 40 => 'critical',
                $b >= 30 => 'warning',
                default => null,
            }, $b < 18.5 ? 'Underweight' : 'Obese');
        }

        // MUAC (cm) — WHO acute-malnutrition cut-offs
        if ($v->muac_cm !== null) {
            $m = (float) $v->muac_cm;
            $add('muac_cm', match (true) {
                $m < 11.5 => 'critical',
                $m < 12.5 => 'warning',
                default => null,
            }, $m < 11.5 ? 'Severe acute malnutrition' : 'Moderate acute malnutrition');
        }

        return [
            'level' => static::worstLevel($flags),
            'flags' => $flags,
        ];
    }

    /**
     * The most severe level present across the flags.
     *
     * @param  array<int, array{metric: string, level: string, label: string}>  $flags
     */
    protected static function worstLevel(array $flags): string
    {
        $levels = array_column($flags, 'level');

        if (in_array('critical', $levels, true)) {
            return 'critical';
        }

        if (in_array('warning', $levels, true)) {
            return 'warning';
        }

        return 'normal';
    }
}

<?php

namespace App\Enums;

/**
 * Interpretive flag for a numeric or qualitative result. Low/High are derived
 * automatically from the reference range; Critical and Abnormal are set by the
 * scientist (panic values / qualitative positives) since they require judgement.
 */
enum ResultFlag: string
{
    case Normal = 'normal';
    case Low = 'low';
    case High = 'high';
    case Critical = 'critical';
    case Abnormal = 'abnormal';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Low => 'Low',
            self::High => 'High',
            self::Critical => 'Critical',
            self::Abnormal => 'Abnormal',
        };
    }

    /**
     * Short marker shown beside a value (H, L, ✕…).
     */
    public function marker(): string
    {
        return match ($this) {
            self::Normal => '',
            self::Low => 'L',
            self::High => 'H',
            self::Critical => '!!',
            self::Abnormal => 'A',
        };
    }

    /**
     * Clinical severity — drives colour, and reuses the vitals palette levels.
     */
    public function severity(): string
    {
        return match ($this) {
            self::Normal => 'normal',
            self::Low, self::High, self::Abnormal => 'warning',
            self::Critical => 'critical',
        };
    }
}

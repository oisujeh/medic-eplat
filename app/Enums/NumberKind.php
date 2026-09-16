<?php

namespace App\Enums;

/**
 * The business numbers the facility issues, each with its own yearly series.
 */
enum NumberKind: string
{
    case Patient = 'patient';
    case Visit = 'visit';
    case LabOrder = 'lab_order';
    case Admission = 'admission';
    case Pregnancy = 'pregnancy';
    case Claim = 'claim';
    case Referral = 'referral';
    case Requisition = 'requisition';
    case Receipt = 'receipt';

    /**
     * The prefix a fresh installation uses. The patient file number's prefix
     * is configurable per facility (PRD REG-01); the others are fixed.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Patient => 'MEP',
            self::Visit => 'V',
            self::LabOrder => 'LAB',
            self::Admission => 'ADM',
            self::Pregnancy => 'PRG',
            self::Claim => 'CLM',
            self::Referral => 'REF',
            self::Requisition => 'REQ',
            self::Receipt => 'RCP',
        };
    }

    /**
     * Whether the facility may set this series' prefix and add a check digit.
     */
    public function isConfigurable(): bool
    {
        return $this === self::Patient;
    }

    /**
     * The printed form of a number: prefix, year and a zero-padded sequence,
     * e.g. MEP/2026/000123, with an optional check digit: MEP/2026/000123-4.
     */
    public function format(int $year, int $sequence, ?string $prefix = null, bool $checkDigit = false): string
    {
        $number = sprintf('%s/%d/%06d', $prefix ?: $this->prefix(), $year, $sequence);

        return $checkDigit ? $number.'-'.self::checkDigitFor(sprintf('%d%06d', $year, $sequence)) : $number;
    }

    /**
     * A Luhn check digit over the numeric part, so a mistyped or misread
     * file number is caught at the desk instead of opening the wrong chart.
     */
    public static function checkDigitFor(string $digits): int
    {
        $sum = 0;
        $double = true;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return (10 - ($sum % 10)) % 10;
    }
}

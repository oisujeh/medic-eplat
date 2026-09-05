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
        };
    }

    /**
     * The printed form of a number: prefix, year and a zero-padded sequence,
     * e.g. MEP/2026/000123.
     */
    public function format(int $year, int $sequence): string
    {
        return sprintf('%s/%d/%06d', $this->prefix(), $year, $sequence);
    }
}

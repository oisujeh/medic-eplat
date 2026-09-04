<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The age groups used on the NHMIS monthly summary form.
 */
class NhmisAgeBands
{
    /**
     * Band keys with their labels, in form order.
     *
     * @var array<string, string>
     */
    public const BANDS = [
        'under_28d' => '< 28 days',
        'under_1y' => '28 days – 11 months',
        'one_to_4' => '1 – 4 years',
        'five_to_9' => '5 – 9 years',
        'ten_to_19' => '10 – 19 years',
        'twenty_plus' => '20 years +',
    ];

    /**
     * The band a person falls in on a given date, or null without a date of
     * birth.
     */
    public static function bandFor(?CarbonInterface $dateOfBirth, CarbonInterface $at): ?string
    {
        if (! $dateOfBirth) {
            return null;
        }

        $days = (int) $dateOfBirth->copy()->startOfDay()->diffInDays($at->copy()->startOfDay());
        $years = (int) $dateOfBirth->copy()->startOfDay()->diffInYears($at->copy()->startOfDay());

        return match (true) {
            $days < 28 => 'under_28d',
            $years < 1 => 'under_1y',
            $years < 5 => 'one_to_4',
            $years < 10 => 'five_to_9',
            $years < 20 => 'ten_to_19',
            default => 'twenty_plus',
        };
    }

    /**
     * Whether a person is under five on a given date; null without a date of
     * birth.
     */
    public static function isUnderFive(?CarbonInterface $dateOfBirth, CarbonInterface $at): ?bool
    {
        if (! $dateOfBirth) {
            return null;
        }

        return $dateOfBirth->copy()->startOfDay()->diffInYears($at->copy()->startOfDay()) < 5;
    }

    /**
     * Whether a person is under one on a given date; null without a date of
     * birth.
     */
    public static function isUnderOne(?CarbonInterface $dateOfBirth, CarbonInterface $at): ?bool
    {
        if (! $dateOfBirth) {
            return null;
        }

        return $dateOfBirth->copy()->startOfDay()->diffInYears($at->copy()->startOfDay()) < 1;
    }
}

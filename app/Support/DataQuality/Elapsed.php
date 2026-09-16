<?php

namespace App\Support\DataQuality;

use Carbon\CarbonInterface;

/**
 * How long ago something happened, in the shortest words that carry it:
 * "3 hours", "2 days".
 */
final class Elapsed
{
    public static function since(CarbonInterface $moment): string
    {
        $hours = (int) $moment->diffInHours(now());

        if ($hours < 48) {
            return $hours.' '.str('hour')->plural($hours);
        }

        $days = (int) $moment->diffInDays(now());

        return $days.' '.str('day')->plural($days);
    }
}

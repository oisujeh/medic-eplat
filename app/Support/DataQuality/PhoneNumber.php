<?php

namespace App\Support\DataQuality;

/**
 * Matching a written phone number against a facility's accepted pattern.
 * People write numbers with spaces, dashes and brackets; none of that makes
 * the number wrong, so it comes out before the pattern is applied.
 */
final class PhoneNumber
{
    /**
     * Whether the number fits the pattern. An unusable pattern (one a person
     * mistyped on the rules screen) matches nothing rather than throwing in
     * the middle of a save.
     */
    public static function matches(string $number, string $pattern): bool
    {
        $digits = preg_replace('/[\s\-().]/', '', $number) ?? $number;

        return @preg_match('/'.str_replace('/', '\/', $pattern).'/', $digits) === 1;
    }
}

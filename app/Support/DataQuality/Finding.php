<?php

namespace App\Support\DataQuality;

/**
 * What a check found wrong with one record: a line a person can act on, and
 * whatever detail helps them act on it without opening the record first.
 */
final class Finding
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $detail = [],
    ) {}
}

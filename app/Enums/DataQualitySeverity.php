<?php

namespace App\Enums;

/**
 * How much an issue matters. Severity is a property of the rule, tuned per
 * facility, and is copied onto an issue when it is raised so the queue reads
 * the same however the rule is retuned later.
 */
enum DataQualitySeverity: string
{
    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::Major => 'Major',
            self::Minor => 'Minor',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::Major => 'amber',
            self::Minor => 'muted',
        };
    }

    /**
     * Ordering weight, most serious first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Major => 1,
            self::Minor => 2,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

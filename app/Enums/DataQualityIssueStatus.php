<?php

namespace App\Enums;

/**
 * Where an issue stands. An issue closes on its own when the record it was
 * raised against passes the rule again; a person only ever waives one.
 */
enum DataQualityIssueStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Waived => 'Waived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Resolved => 'green',
            self::Waived => 'muted',
        };
    }

    /**
     * Whether the issue still counts against the record.
     */
    public function isOutstanding(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

<?php

namespace App\Enums;

/**
 * What a data-quality rule judges. The four dimensions the scorecard reports
 * on: whether the record says everything it should, whether what it says is
 * possible, whether the same thing was recorded twice, and whether it was
 * recorded in time.
 */
enum DataQualityDimension: string
{
    case Completeness = 'completeness';
    case Validity = 'validity';
    case Duplicate = 'duplicate';
    case Timeliness = 'timeliness';

    public function label(): string
    {
        return match ($this) {
            self::Completeness => 'Completeness',
            self::Validity => 'Validity',
            self::Duplicate => 'Duplicates',
            self::Timeliness => 'Timeliness',
        };
    }

    /**
     * What a failure of this dimension means, shown on the scorecard.
     */
    public function description(): string
    {
        return match ($this) {
            self::Completeness => 'Records that leave out something they should carry.',
            self::Validity => 'Records that say something impossible or malformed.',
            self::Duplicate => 'The same patient or identifier recorded more than once.',
            self::Timeliness => 'Work left undone past the time it should have been finished.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Completeness => 'ClipboardList',
            self::Validity => 'ShieldAlert',
            self::Duplicate => 'CopyCheck',
            self::Timeliness => 'Timer',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $d) => ['value' => $d->value, 'label' => $d->label()], self::cases());
    }
}

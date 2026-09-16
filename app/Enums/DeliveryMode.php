<?php

namespace App\Enums;

/**
 * How a baby was delivered.
 */
enum DeliveryMode: string
{
    case Svd = 'svd';
    case AssistedVacuum = 'assisted_vacuum';
    case AssistedForceps = 'assisted_forceps';
    case Breech = 'breech';
    case CsElective = 'cs_elective';
    case CsEmergency = 'cs_emergency';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Svd => 'Spontaneous vaginal delivery',
            self::AssistedVacuum => 'Assisted delivery (vacuum)',
            self::AssistedForceps => 'Assisted delivery (forceps)',
            self::Breech => 'Assisted breech delivery',
            self::CsElective => 'Caesarean section (elective)',
            self::CsEmergency => 'Caesarean section (emergency)',
        };
    }

    /**
     * Whether the delivery was by caesarean section.
     */
    public function isCaesarean(): bool
    {
        return in_array($this, [self::CsElective, self::CsEmergency], true);
    }

    /**
     * Options for a select control.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}

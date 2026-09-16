<?php

namespace App\Enums;

enum ObservationPanel: string
{
    case Vitals = 'vitals';
    case Anthropometrics = 'anthropometrics';
    case Antenatal = 'antenatal';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Vitals => 'Vitals',
            self::Anthropometrics => 'Anthropometrics',
            self::Antenatal => 'Antenatal',
        };
    }

    /**
     * The codes captured under this panel, in entry order.
     *
     * @return array<int, ObservationCode>
     */
    public function codes(): array
    {
        return array_values(array_filter(
            ObservationCode::cases(),
            fn (ObservationCode $c) => $c->panel() === $this,
        ));
    }
}

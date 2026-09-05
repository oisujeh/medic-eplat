<?php

namespace App\Support;

use App\Enums\MatchLevel;
use App\Enums\MatchSignal;
use App\Models\Patient;

/**
 * How closely an existing record matches the details being compared.
 */
final class MatchResult
{
    /**
     * @param  list<MatchSignal>  $signals
     */
    public function __construct(
        public readonly Patient $patient,
        public readonly int $score,
        public readonly MatchLevel $level,
        public readonly array $signals,
    ) {}

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return array_map(fn (MatchSignal $s) => $s->label(), $this->signals);
    }

    /**
     * @return list<string>
     */
    public function signalValues(): array
    {
        return array_map(fn (MatchSignal $s) => $s->value, $this->signals);
    }
}

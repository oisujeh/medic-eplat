<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Normal = 'normal';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Severity rank, higher is worse.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Normal => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }

    /**
     * The worse of two levels.
     */
    public function max(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }

    /**
     * The worst level among a list, normal when the list is empty.
     *
     * @param  iterable<int, self|null>  $levels
     */
    public static function worst(iterable $levels): self
    {
        $worst = self::Normal;

        foreach ($levels as $level) {
            if ($level !== null) {
                $worst = $worst->max($level);
            }
        }

        return $worst;
    }
}

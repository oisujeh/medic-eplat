<?php

namespace App\Enums;

/**
 * The IDSR case classification as the investigation progresses.
 *
 * The lifecycle runs Suspected → Probable → Confirmed, with Discarded
 * available until a case is confirmed. A discarded case can only be
 * reopened as Suspected. Every change is written to the audit trail with
 * its before and after values.
 */
enum CaseClassification: string
{
    case Suspected = 'suspected';
    case Probable = 'probable';
    case Confirmed = 'confirmed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Suspected => 'Suspected',
            self::Probable => 'Probable',
            self::Confirmed => 'Confirmed',
            self::Discarded => 'Discarded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Suspected => 'amber',
            self::Probable => 'amber',
            self::Confirmed => 'red',
            self::Discarded => 'muted',
        };
    }

    /**
     * Whether the case still counts on registers and returns.
     */
    public function isOpen(): bool
    {
        return $this !== self::Discarded;
    }

    /**
     * The classifications this one may move to.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Suspected => [self::Probable, self::Confirmed, self::Discarded],
            self::Probable => [self::Confirmed, self::Discarded],
            self::Confirmed => [],
            self::Discarded => [self::Suspected],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $to === $this || in_array($to, $this->transitions(), true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    /**
     * The options a case in this classification may be set to, itself first.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function availableOptions(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label()],
            [$this, ...$this->transitions()],
        );
    }
}

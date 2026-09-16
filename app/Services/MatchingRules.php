<?php

namespace App\Services;

use App\Enums\MatchSignal;
use App\Models\GlobalProperty;
use App\Models\MatchingRule;

/**
 * The configuration behind patient matching: what each signal is worth and
 * where the score bands fall. Everything here is editable data; the values
 * in code are only the defaults for a fresh installation.
 */
class MatchingRules
{
    public const KEY_AUTO_THRESHOLD = 'dedup.auto_threshold';

    public const KEY_REVIEW_THRESHOLD = 'dedup.review_threshold';

    public const KEY_HINT_THRESHOLD = 'dedup.hint_threshold';

    public const DEFAULT_AUTO_THRESHOLD = 100;

    public const DEFAULT_REVIEW_THRESHOLD = 55;

    public const DEFAULT_HINT_THRESHOLD = 30;

    /**
     * @var array<string, int>|null
     */
    private ?array $weights = null;

    /**
     * The weight a signal adds to the score, or zero when switched off.
     */
    public function weight(MatchSignal $signal): int
    {
        $this->weights ??= $this->loadWeights();

        return $this->weights[$signal->value] ?? $signal->defaultWeight();
    }

    public function autoThreshold(): int
    {
        return (int) GlobalProperty::valueOf(self::KEY_AUTO_THRESHOLD, (string) self::DEFAULT_AUTO_THRESHOLD);
    }

    public function reviewThreshold(): int
    {
        return (int) GlobalProperty::valueOf(self::KEY_REVIEW_THRESHOLD, (string) self::DEFAULT_REVIEW_THRESHOLD);
    }

    public function hintThreshold(): int
    {
        return (int) GlobalProperty::valueOf(self::KEY_HINT_THRESHOLD, (string) self::DEFAULT_HINT_THRESHOLD);
    }

    /**
     * Write the thresholds, e.g. from an administration screen or a seeder.
     */
    public function saveThresholds(int $auto, int $review, int $hint): void
    {
        GlobalProperty::put(self::KEY_AUTO_THRESHOLD, (string) $auto, 'Match score at or above which two records are almost certainly one person.');
        GlobalProperty::put(self::KEY_REVIEW_THRESHOLD, (string) $review, 'Match score at or above which a pair is queued for review.');
        GlobalProperty::put(self::KEY_HINT_THRESHOLD, (string) $hint, 'Match score at or above which a record is shown to the registrar as a possible match.');
    }

    /**
     * Drop the cached weights so a changed rule applies to the next score.
     */
    public function forget(): void
    {
        $this->weights = null;
    }

    /**
     * @return array<string, int>
     */
    private function loadWeights(): array
    {
        $weights = [];

        foreach (MatchingRule::query()->get() as $rule) {
            $weights[$rule->signal->value] = $rule->is_active ? $rule->weight : 0;
        }

        return $weights;
    }
}

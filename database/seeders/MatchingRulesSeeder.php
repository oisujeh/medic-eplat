<?php

namespace Database\Seeders;

use App\Enums\MatchSignal;
use App\Models\GlobalProperty;
use App\Models\MatchingRule;
use App\Services\MatchingRules;
use Illuminate\Database\Seeder;

/**
 * The default deduplication weights and thresholds. Idempotent: a weight an
 * administrator has changed is kept.
 */
class MatchingRulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MatchSignal::cases() as $signal) {
            MatchingRule::query()->firstOrCreate(
                ['signal' => $signal->value],
                ['weight' => $signal->defaultWeight(), 'is_active' => true],
            );
        }

        $rules = app(MatchingRules::class);

        if (GlobalProperty::valueOf(MatchingRules::KEY_REVIEW_THRESHOLD) === null) {
            $rules->saveThresholds(
                MatchingRules::DEFAULT_AUTO_THRESHOLD,
                MatchingRules::DEFAULT_REVIEW_THRESHOLD,
                MatchingRules::DEFAULT_HINT_THRESHOLD,
            );
        }
    }
}

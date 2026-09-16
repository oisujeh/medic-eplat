<?php

namespace Database\Seeders;

use App\Models\DataQualityRule;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\DataQualityChecks;
use Illuminate\Database\Seeder;

/**
 * A registry row for every check the code carries, starting from the defaults
 * the check declares. Editable seed data: a facility retunes severity, the
 * owning role and the thresholds, or switches a rule off entirely, and
 * reseeding leaves those choices alone.
 */
class DataQualityRulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DataQualityChecks::all()->values() as $index => $check) {
            /** @var DataQualityCheck $check */
            $existing = DataQualityRule::query()->where('key', $check::key())->first();

            if ($existing !== null) {
                // Only what the facility does not own is refreshed: the wording
                // of the rule, and any threshold the check has since added.
                $existing->update([
                    'name' => $check::name(),
                    'description' => $check::description(),
                    'parameters' => [...$check::defaultParameters(), ...$existing->parameters],
                ]);

                continue;
            }

            DataQualityRule::create([
                'key' => $check::key(),
                'name' => $check::name(),
                'dimension' => $check::dimension(),
                'severity' => $check::defaultSeverity(),
                'description' => $check::description(),
                'owner_role' => $check::defaultOwnerRole(),
                'parameters' => $check::defaultParameters(),
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}

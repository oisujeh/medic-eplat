<?php

namespace App\Services;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualitySeverity;
use App\Models\DataQualityIssue;
use App\Models\DataQualityRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the data-quality engine has to say about the register as a whole: how
 * many records pass each rule, where the failures are concentrated, and
 * whether the facility is closing issues faster than it is raising them.
 *
 * A pass rate is measured against the records a rule actually judges — the
 * folders it looks at, the encounters, the claims — not against everything in
 * the database, so the rates of two rules can be read side by side.
 */
class DataQualityMetrics
{
    public function __construct(private readonly DataQuality $engine) {}

    /**
     * The whole scorecard for a period.
     *
     * @return array{summary: array<string, mixed>, dimensions: array<int, array<string, mixed>>, rules: array<int, array<string, mixed>>, trend: array<int, array<string, mixed>>}
     */
    public function scorecard(Carbon $from, Carbon $to): array
    {
        $rules = collect($this->ruleMetrics());

        return [
            'summary' => $this->summary($from, $to, $rules),
            'dimensions' => $this->dimensions($rules),
            'rules' => $rules->all(),
            'trend' => $this->trend($from, $to),
        ];
    }

    /**
     * Every active rule with how the records it judges are doing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ruleMetrics(): array
    {
        $open = DataQualityIssue::query()
            ->open()
            ->selectRaw('data_quality_rule_id, count(*) as total')
            ->groupBy('data_quality_rule_id')
            ->pluck('total', 'data_quality_rule_id');

        $waived = DataQualityIssue::query()
            ->where('status', DataQualityIssueStatus::Waived)
            ->selectRaw('data_quality_rule_id, count(*) as total')
            ->groupBy('data_quality_rule_id')
            ->pluck('total', 'data_quality_rule_id');

        return $this->engine->rules()->map(function (DataQualityRule $rule) use ($open, $waived): array {
            $check = $rule->check();
            $population = $check === null ? 0 : $check->population($rule->effectiveParameters());
            $failing = (int) ($open[$rule->id] ?? 0);

            return [
                'id' => $rule->id,
                'key' => $rule->key,
                'name' => $rule->name,
                'dimension' => $rule->dimension->value,
                'dimension_label' => $rule->dimension->label(),
                'severity' => $rule->severity->value,
                'severity_label' => $rule->severity->label(),
                'severity_tone' => $rule->severity->tone(),
                'owner_role' => $rule->owner_role,
                'open' => $failing,
                'waived' => (int) ($waived[$rule->id] ?? 0),
                'population' => $population,
                'pass_rate' => $this->rate($population - $failing, $population),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    private function dimensions(Collection $rules): array
    {
        return collect(DataQualityDimension::cases())
            ->map(function (DataQualityDimension $dimension) use ($rules): array {
                $own = $rules->where('dimension', $dimension->value);
                $population = (int) $own->sum('population');
                $open = (int) $own->sum('open');

                return [
                    'value' => $dimension->value,
                    'label' => $dimension->label(),
                    'description' => $dimension->description(),
                    'icon' => $dimension->icon(),
                    'rules' => $own->count(),
                    'open' => $open,
                    'population' => $population,
                    'pass_rate' => $this->rate($population - $open, $population),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function summary(Carbon $from, Carbon $to, Collection $rules): array
    {
        $population = (int) $rules->sum('population');
        $open = (int) $rules->sum('open');

        $oldest = DataQualityIssue::query()->open()->min('first_detected_at');

        return [
            'open' => $open,
            'critical' => DataQualityIssue::query()->open()->where('severity', DataQualitySeverity::Critical)->count(),
            'waived' => (int) $rules->sum('waived'),
            'raised' => DataQualityIssue::query()->whereBetween('first_detected_at', [$from, $to])->count(),
            'resolved' => DataQualityIssue::query()
                ->where('status', DataQualityIssueStatus::Resolved)
                ->whereBetween('resolved_at', [$from, $to])
                ->count(),
            'pass_rate' => $this->rate($population - $open, $population),
            'records' => $population,
            'oldest_open' => $oldest === null ? null : Carbon::parse($oldest)->isoFormat('D MMM YYYY'),
        ];
    }

    /**
     * Issues raised against issues closed, day by day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trend(Carbon $from, Carbon $to): array
    {
        $raised = $this->countByDay('first_detected_at', $from, $to);
        $resolved = DataQualityIssue::query()
            ->where('status', DataQualityIssueStatus::Resolved)
            ->whereBetween('resolved_at', [$from, $to])
            ->get(['resolved_at'])
            ->countBy(fn (DataQualityIssue $issue) => (string) $issue->resolved_at?->format('Y-m-d'));

        $days = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'label' => $day->isoFormat('D MMM'),
                'raised' => (int) ($raised[$key] ?? 0),
                'resolved' => (int) ($resolved[$key] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return Collection<string, int>
     */
    private function countByDay(string $column, Carbon $from, Carbon $to): Collection
    {
        return DataQualityIssue::query()
            ->whereBetween($column, [$from, $to])
            ->get([$column])
            ->countBy(fn (DataQualityIssue $issue) => (string) $issue->getAttribute($column)?->format('Y-m-d'));
    }

    /**
     * A pass rate to one decimal place. No records to judge is not a failure:
     * a rule with nothing in scope reads as 100%.
     */
    private function rate(int $passing, int $population): float
    {
        if ($population <= 0) {
            return 100.0;
        }

        return round(max($passing, 0) / $population * 100, 1);
    }
}

<?php

namespace App\Services;

use App\Enums\DataQualityIssueStatus;
use App\Models\DataQualityIssue;
use Illuminate\Support\Carbon;

/**
 * The data-quality reports: the issues raised over a period, and how each
 * rule is doing against the records it judges.
 */
class DataQualityReports
{
    public function __construct(private readonly DataQualityMetrics $metrics) {}

    /**
     * Every issue raised within the period, whatever became of it.
     *
     * @return array<string, mixed>
     */
    public function issueRegister(Carbon $from, Carbon $to): array
    {
        $issues = DataQualityIssue::query()
            ->with(['rule:id,name', 'patient:id,file_number,surname,first_name,other_names'])
            ->whereBetween('first_detected_at', [$from, $to])
            ->orderBy('first_detected_at')
            ->get();

        $rows = $issues
            ->map(fn (DataQualityIssue $issue) => [
                'detected' => $issue->first_detected_at->isoFormat('D MMM YYYY'),
                'rule' => $issue->rule->name,
                'dimension' => $issue->dimension->label(),
                'severity' => $issue->severity->label(),
                'record' => class_basename($issue->subject_type).' #'.$issue->subject_id,
                'patient' => $issue->patient?->fullName() ?? '—',
                'file_number' => $issue->patient?->file_number ?? '—',
                'summary' => $issue->summary,
                'status' => $issue->status->label(),
                'closed' => $issue->resolved_at?->isoFormat('D MMM YYYY') ?? '—',
            ])
            ->all();

        return [
            'columns' => [
                ['key' => 'detected', 'label' => 'Detected', 'align' => 'left'],
                ['key' => 'rule', 'label' => 'Rule', 'align' => 'left'],
                ['key' => 'dimension', 'label' => 'Dimension', 'align' => 'left'],
                ['key' => 'severity', 'label' => 'Severity', 'align' => 'left'],
                ['key' => 'record', 'label' => 'Record', 'align' => 'left'],
                ['key' => 'patient', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'file_number', 'label' => 'File number', 'align' => 'left'],
                ['key' => 'summary', 'label' => 'What is wrong', 'align' => 'left'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'left'],
                ['key' => 'closed', 'label' => 'Closed', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Issues raised', 'value' => (string) $issues->count()],
                ['label' => 'Still open', 'value' => (string) $issues->where('status', DataQualityIssueStatus::Open)->count()],
                ['label' => 'Waived', 'value' => (string) $issues->where('status', DataQualityIssueStatus::Waived)->count()],
            ],
        ];
    }

    /**
     * Each rule with the share of the records it judges that pass it.
     *
     * @return array<string, mixed>
     */
    public function ruleScorecard(Carbon $from, Carbon $to): array
    {
        $rules = collect($this->metrics->ruleMetrics());

        $rows = $rules
            ->sortBy('pass_rate')
            ->map(fn (array $rule) => [
                'rule' => $rule['name'],
                'dimension' => $rule['dimension_label'],
                'severity' => $rule['severity_label'],
                'population' => (string) $rule['population'],
                'open' => (string) $rule['open'],
                'waived' => (string) $rule['waived'],
                'pass_rate' => number_format($rule['pass_rate'], 1).'%',
            ])
            ->values()
            ->all();

        $population = (int) $rules->sum('population');
        $open = (int) $rules->sum('open');

        return [
            'columns' => [
                ['key' => 'rule', 'label' => 'Rule', 'align' => 'left'],
                ['key' => 'dimension', 'label' => 'Dimension', 'align' => 'left'],
                ['key' => 'severity', 'label' => 'Severity', 'align' => 'left'],
                ['key' => 'population', 'label' => 'Records judged', 'align' => 'right'],
                ['key' => 'open', 'label' => 'Failing', 'align' => 'right'],
                ['key' => 'waived', 'label' => 'Waived', 'align' => 'right'],
                ['key' => 'pass_rate', 'label' => 'Pass rate', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Rules in force', 'value' => (string) $rules->count()],
                ['label' => 'Records failing a rule', 'value' => (string) $open],
                ['label' => 'Overall pass rate', 'value' => $population === 0
                    ? '100.0%'
                    : number_format(max($population - $open, 0) / $population * 100, 1).'%'],
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualitySeverity;
use App\Http\Requests\WaiveDataQualityIssueRequest;
use App\Models\DataQualityIssue;
use App\Models\DataQualityRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\DataQuality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The data-quality work queue: every record currently failing a rule, with
 * the screen that puts it right one click away.
 */
class DataQualityController extends Controller
{
    public function __construct(private readonly DataQuality $dataQuality) {}

    /**
     * The queue. A user's own work comes up first: the issues routed to one
     * of their roles, and those routed to nobody.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'rule' => ['nullable', 'integer'],
            'dimension' => ['nullable', 'string'],
            'severity' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'owner' => ['nullable', 'string'],
        ]);

        $roleSlugs = $request->user()->roles->pluck('slug')->all();

        $filters = [
            'dimension' => (string) $request->string('dimension'),
            'severity' => (string) $request->string('severity'),
            'status' => (string) $request->string('status'),
            'rule' => $request->integer('rule') ?: null,
            'owner' => (string) $request->string('owner'),
            'search' => trim((string) $request->string('search')),
        ];

        $status = DataQualityIssueStatus::tryFrom($filters['status']) ?? DataQualityIssueStatus::Open;

        $issues = DataQualityIssue::query()
            ->with(['rule', 'subject', 'patient:id,file_number,surname,first_name,other_names', 'resolvedBy:id,name'])
            ->where('status', $status)
            ->when($filters['dimension'] !== '', fn (Builder $q) => $q->where('dimension', $filters['dimension']))
            ->when($filters['severity'] !== '', fn (Builder $q) => $q->where('severity', $filters['severity']))
            ->when($filters['rule'], fn (Builder $q) => $q->where('data_quality_rule_id', $filters['rule']))
            ->when($filters['owner'] === 'mine', fn (Builder $q) => $q->ownedByAny($roleSlugs))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = "%{$filters['search']}%";

                $query->where(fn (Builder $q) => $q
                    ->where('summary', 'like', $term)
                    ->orWhereIn('patient_id', Patient::query()
                        ->select('id')
                        ->where(fn (Builder $p) => $p
                            ->where('file_number', 'like', $term)
                            ->orWhere('surname', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('other_names', 'like', $term))));
            })
            // Most serious first, then the ones that have waited longest.
            ->orderByRaw("case severity when 'critical' then 0 when 'major' then 1 else 2 end")
            ->orderBy('first_detected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DataQualityIssue $issue) => $this->row($issue, $this->roleNames()));

        return Inertia::render('data-quality/Issues', [
            'issues' => $issues,
            'filters' => $filters,
            'summary' => $this->summary($request->user()),
            'rules' => DataQualityRule::query()
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (DataQualityRule $rule) => ['id' => $rule->id, 'name' => $rule->name]),
            'dimensions' => DataQualityDimension::options(),
            'severities' => DataQualitySeverity::options(),
            'statuses' => DataQualityIssueStatus::options(),
            'can' => [
                'waive' => $request->user()->can('data-quality.waive_issues'),
                'manage_rules' => $request->user()->can('data-quality.manage_rules'),
            ],
        ]);
    }

    /**
     * Check the record again now. A person who has just put it right sees the
     * queue agree without waiting for the nightly sweep.
     */
    public function recheck(DataQualityIssue $issue): RedirectResponse
    {
        $passes = $this->dataQuality->recheck($issue);

        Inertia::flash('toast', [
            'type' => $passes ? 'success' : 'info',
            'message' => $passes
                ? 'The record passes now; the issue is closed.'
                : 'The record still fails this rule.',
        ]);

        return back();
    }

    /**
     * Put the issue aside with a reason.
     */
    public function waive(WaiveDataQualityIssueRequest $request, DataQualityIssue $issue): RedirectResponse
    {
        abort_unless($issue->status === DataQualityIssueStatus::Open, 422, 'Only an open issue can be waived.');

        $this->dataQuality->waive($issue, $request->user(), $request->validated()['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Issue waived.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $mine = $this->dataQuality->outstandingFor($user);

        return [
            'open' => DataQualityIssue::query()->open()->count(),
            'critical' => DataQualityIssue::query()->open()->where('severity', DataQualitySeverity::Critical)->count(),
            'mine' => $mine['total'],
            'waived' => DataQualityIssue::query()->where('status', DataQualityIssueStatus::Waived)->count(),
        ];
    }

    /**
     * Role names by slug, for showing who owns an issue.
     *
     * @return Collection<string, string>
     */
    private function roleNames(): Collection
    {
        return once(fn () => Role::query()->pluck('name', 'slug'));
    }

    /**
     * @param  Collection<string, string>  $roleNames
     * @return array<string, mixed>
     */
    private function row(DataQualityIssue $issue, Collection $roleNames): array
    {
        $subject = $issue->subject;
        $patient = $issue->patient;

        return [
            'id' => $issue->id,
            'rule' => $issue->rule->name,
            'dimension' => $issue->dimension->value,
            'dimension_label' => $issue->dimension->label(),
            'severity' => $issue->severity->value,
            'severity_label' => $issue->severity->label(),
            'severity_tone' => $issue->severity->tone(),
            'status' => $issue->status->value,
            'status_label' => $issue->status->label(),
            'status_tone' => $issue->status->tone(),
            'summary' => $issue->summary,
            'detail' => $issue->detail,
            'record' => $this->recordLabel($issue, $subject),
            'href' => $subject instanceof Model ? $issue->rule->check()?->href($subject) : null,
            'patient' => $patient === null ? null : [
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
                'href' => route('patients.show', $patient),
            ],
            'owner' => $issue->owner_role === null ? null : ($roleNames[$issue->owner_role] ?? $issue->owner_role),
            'first_detected_at' => $issue->first_detected_at->isoFormat('D MMM YYYY'),
            'age_days' => $issue->ageInDays(),
            'resolution' => $issue->resolution,
            'resolved_at' => $issue->resolved_at?->isoFormat('D MMM YYYY'),
            'resolved_by' => $issue->resolvedBy?->name,
        ];
    }

    /**
     * What the issue was raised against, in words: "Encounter #412".
     */
    private function recordLabel(DataQualityIssue $issue, ?Model $subject): string
    {
        if ($subject === null) {
            return 'Record removed';
        }

        return str(class_basename($subject))->headline()->toString().' #'.$subject->getKey();
    }
}

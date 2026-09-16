<?php

namespace App\Services;

use App\Enums\DataQualityIssueStatus;
use App\Models\DataQualityIssue;
use App\Models\DataQualityRule;
use App\Models\User;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\Finding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The data-quality engine: it applies the active rules to records and keeps
 * one issue per record per rule.
 *
 * Two things set it going. A record being written is judged at once by the
 * rules that can decide there and then, through the EvaluateDataQuality
 * listener. Everything else — the rules that measure elapsed time, and the
 * re-check that closes issues somebody has since put right — waits for the
 * nightly sweep.
 *
 * An issue closes on its own the moment its record passes again; a person
 * only ever waives one.
 */
class DataQuality
{
    /**
     * The active rules whose check still exists in the code.
     *
     * Read fresh on each call: a service instance can outlive a request, and
     * a rule switched off must stop raising issues at once.
     *
     * @return Collection<int, DataQualityRule>
     */
    public function rules(): Collection
    {
        return DataQualityRule::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (DataQualityRule $rule) => $rule->check() !== null)
            ->values();
    }

    /**
     * Judge a record against every rule that can decide as it is written.
     *
     * Called from the domain-event listener inside the writing transaction,
     * so a write that rolls back takes its issues with it.
     */
    public function evaluate(Model $subject): void
    {
        foreach ($this->rules() as $rule) {
            $check = $rule->check();

            if ($check === null || ! $check::runsOnWrite() || ! $check->judges($subject)) {
                continue;
            }

            $this->apply($rule, $check, $subject);
        }
    }

    /**
     * Run every rule over the records it judges, then re-check the issues
     * already open so those that have been put right close.
     *
     * @return array{checked: int, raised: int, resolved: int}
     */
    public function sweep(): array
    {
        $checked = 0;
        $raised = 0;
        // The issues this pass has looked at, so the re-check below does not
        // judge the same record twice.
        $visited = [];

        foreach ($this->rules() as $rule) {
            $check = $rule->check();

            if ($check === null) {
                continue;
            }

            $parameters = $rule->effectiveParameters();

            foreach ($check->candidates($parameters) as $subject) {
                $checked++;
                [$outcome, $issue] = $this->apply($rule, $check, $subject, $parameters);

                if ($issue !== null) {
                    $visited[$issue->id] = true;
                }

                if ($outcome === 'raised') {
                    $raised++;
                }
            }
        }

        return [
            'checked' => $checked,
            'raised' => $raised,
            'resolved' => $this->closeIssuesThatNowPass($visited),
        ];
    }

    /**
     * Put an issue aside with a reason: the record is as it should be, or
     * cannot be put right, and the queue should stop showing it.
     */
    public function waive(DataQualityIssue $issue, User $actor, string $reason): void
    {
        $issue->update([
            'status' => DataQualityIssueStatus::Waived,
            'resolved_at' => now(),
            'resolved_by' => $actor->id,
            'resolution' => $reason,
        ]);
    }

    /**
     * Re-check one issue's record now, so a person who has just fixed it can
     * see the queue agree.
     *
     * @return bool whether the record now passes
     */
    public function recheck(DataQualityIssue $issue): bool
    {
        $rule = $issue->rule;
        $check = $rule->check();
        $subject = $issue->subject;

        if ($check === null || ! $subject instanceof Model) {
            $this->close($issue, 'The record this was raised against no longer exists.');

            return true;
        }

        $finding = $check->check($subject, $rule->effectiveParameters());

        if ($finding === null) {
            $this->close($issue, 'The record passes the rule.');

            return true;
        }

        $issue->update(['summary' => $finding->summary, 'detail' => $finding->detail, 'last_seen_at' => now()]);

        return false;
    }

    /**
     * Open issues a user is answerable for: those routed to one of their
     * roles, and those routed to nobody.
     *
     * @return array{total: int, critical: int}
     */
    public function outstandingFor(User $user): array
    {
        $issues = DataQualityIssue::query()
            ->open()
            ->ownedByAny($user->roles->pluck('slug')->all())
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when severity = 'critical' then 1 else 0 end) as critical")
            ->first();

        return [
            'total' => (int) ($issues?->getAttribute('total') ?? 0),
            'critical' => (int) ($issues?->getAttribute('critical') ?? 0),
        ];
    }

    /**
     * Judge one record against one rule and reconcile the issue that goes
     * with it.
     *
     * @param  array<string, mixed>|null  $parameters
     * @return array{0: 'raised'|'touched'|'resolved'|'passed', 1: DataQualityIssue|null}
     */
    private function apply(DataQualityRule $rule, DataQualityCheck $check, Model $subject, ?array $parameters = null): array
    {
        $finding = $check->check($subject, $parameters ?? $rule->effectiveParameters());
        $issue = $this->existingIssue($rule, $subject);

        if ($finding === null) {
            if ($issue !== null && $issue->status === DataQualityIssueStatus::Open) {
                $this->close($issue, 'The record passes the rule.');

                return ['resolved', $issue];
            }

            return ['passed', $issue];
        }

        if ($issue === null) {
            return ['raised', $this->open($rule, $check, $subject, $finding)];
        }

        $this->touch($rule, $issue, $finding);

        return [$issue->wasChanged('status') ? 'raised' : 'touched', $issue];
    }

    private function existingIssue(DataQualityRule $rule, Model $subject): ?DataQualityIssue
    {
        return DataQualityIssue::query()
            ->where('data_quality_rule_id', $rule->id)
            ->whereMorphedTo('subject', $subject)
            ->first();
    }

    private function open(DataQualityRule $rule, DataQualityCheck $check, Model $subject, Finding $finding): DataQualityIssue
    {
        $now = now();

        return DataQualityIssue::create([
            'data_quality_rule_id' => $rule->id,
            'catalogue_version_id' => $rule->currentVersion()->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'patient_id' => $check->patientIdFor($subject),
            'dimension' => $rule->dimension,
            'severity' => $rule->severity,
            'owner_role' => $rule->owner_role,
            'summary' => mb_substr($finding->summary, 0, 300),
            'detail' => $finding->detail === [] ? null : $finding->detail,
            'status' => DataQualityIssueStatus::Open,
            'first_detected_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * The record still fails. A waived issue stays waived; a resolved one
     * that has gone wrong again reopens, keeping the date it first appeared.
     */
    private function touch(DataQualityRule $rule, DataQualityIssue $issue, Finding $finding): void
    {
        if ($issue->status === DataQualityIssueStatus::Waived) {
            return;
        }

        $changes = [
            'summary' => mb_substr($finding->summary, 0, 300),
            'detail' => $finding->detail === [] ? null : $finding->detail,
            'last_seen_at' => now(),
        ];

        if ($issue->status === DataQualityIssueStatus::Resolved) {
            $changes += [
                'status' => DataQualityIssueStatus::Open,
                'catalogue_version_id' => $rule->currentVersion()->id,
                'severity' => $rule->severity,
                'owner_role' => $rule->owner_role,
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution' => null,
            ];
        }

        $issue->update($changes);
    }

    private function close(DataQualityIssue $issue, string $resolution): void
    {
        $issue->update([
            'status' => DataQualityIssueStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => null,
            'resolution' => $resolution,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Close the open issues whose records now pass. The sweep only visits the
     * records a rule considers suspect, so an issue on a record that has
     * since been put right would otherwise sit open for ever.
     *
     * @param  array<int, bool>  $visited  Issue ids this sweep has already judged.
     */
    private function closeIssuesThatNowPass(array $visited): int
    {
        $resolved = 0;

        DataQualityIssue::query()
            ->open()
            ->with(['rule', 'subject'])
            ->lazyById(200)
            ->each(function (DataQualityIssue $issue) use ($visited, &$resolved): void {
                // Anything the pass above looked at was found still failing.
                if (isset($visited[$issue->id])) {
                    return;
                }

                if ($this->recheck($issue)) {
                    $resolved++;
                }
            });

        return $resolved;
    }
}

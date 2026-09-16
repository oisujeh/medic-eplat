<?php

namespace App\Services;

use App\Enums\CaseNotificationStatus;
use App\Enums\CaseOutcome;
use App\Models\NotifiableDisease;
use App\Models\SurveillanceCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The IDSR returns: the case-based line list and the weekly summary by
 * disease, each as a table the report runner can render and export.
 *
 * Every figure comes from the snapshot on the case (category, residence,
 * deadline), not from the live catalogue or folder, so a return re-run
 * months later reads the same.
 */
class SurveillanceReports
{
    /**
     * IDSR line list: one row per open case detected in the range.
     *
     * @return array<string, mixed>
     */
    public function lineList(Carbon $from, Carbon $to): array
    {
        $cases = $this->casesIn($from, $to);

        $rows = $cases->map(fn (SurveillanceCase $case) => [
            'detected' => $case->detected_at->isoFormat('D MMM YYYY'),
            'disease' => $case->disease->name,
            'category' => $case->category->label(),
            'file_number' => $case->patient->file_number,
            'name' => $case->patient->fullName(),
            'sex' => $case->patient->sex,
            'age' => $case->patient->age() !== null ? $case->patient->age().'y' : '—',
            'lga' => $case->residence_lga ?? '—',
            'state' => $case->residence_state ?? '—',
            'phone' => $case->patient->phone ?? '—',
            'onset' => $case->onset_date?->isoFormat('D MMM YYYY') ?? '—',
            'code' => $case->icd_code ?? '—',
            'classification' => $case->classification->label(),
            'outcome' => $case->outcome->label(),
            'notification' => $case->notified_at
                ? 'Notified '.$case->notified_at->isoFormat('D MMM YYYY')
                : $case->notification_status->label(),
            'timeliness' => $this->timeliness($case),
        ])->all();

        $immediate = $cases->filter(fn (SurveillanceCase $c) => $c->notification_due_at !== null);

        return [
            'columns' => [
                ['key' => 'detected', 'label' => 'Detected', 'align' => 'left'],
                ['key' => 'disease', 'label' => 'Disease', 'align' => 'left'],
                ['key' => 'category', 'label' => 'Category', 'align' => 'left'],
                ['key' => 'file_number', 'label' => 'File no.', 'align' => 'left'],
                ['key' => 'name', 'label' => 'Patient', 'align' => 'left'],
                ['key' => 'sex', 'label' => 'Sex', 'align' => 'left'],
                ['key' => 'age', 'label' => 'Age', 'align' => 'right'],
                ['key' => 'lga', 'label' => 'LGA', 'align' => 'left'],
                ['key' => 'state', 'label' => 'State', 'align' => 'left'],
                ['key' => 'phone', 'label' => 'Phone', 'align' => 'left'],
                ['key' => 'onset', 'label' => 'Onset', 'align' => 'left'],
                ['key' => 'code', 'label' => 'ICD-10', 'align' => 'left'],
                ['key' => 'classification', 'label' => 'Classification', 'align' => 'left'],
                ['key' => 'outcome', 'label' => 'Outcome', 'align' => 'left'],
                ['key' => 'notification', 'label' => 'DSNO notification', 'align' => 'left'],
                ['key' => 'timeliness', 'label' => 'Timeliness', 'align' => 'left'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Cases', 'value' => (string) $cases->count()],
                ['label' => 'Deaths', 'value' => (string) $cases->where('outcome', CaseOutcome::Dead)->count()],
                ['label' => 'Awaiting notification', 'value' => (string) $cases->where('notification_status', CaseNotificationStatus::Pending)->count()],
                ['label' => 'Notified on time', 'value' => $immediate->isEmpty()
                    ? '—'
                    : $immediate->filter(fn (SurveillanceCase $c) => $c->notificationPhase() === SurveillanceCase::PHASE_NOTIFIED)->count().' of '.$immediate->count()],
            ],
        ];
    }

    /**
     * IDSR weekly summary: cases and deaths per priority disease. Every
     * active catalogue entry is listed so zero-reporting is explicit, and a
     * deactivated entry stays on the return for any period it has cases in.
     *
     * @return array<string, mixed>
     */
    public function weeklySummary(Carbon $from, Carbon $to): array
    {
        $cases = $this->casesIn($from, $to)->groupBy('notifiable_disease_id');

        $rows = NotifiableDisease::query()
            ->where(fn (Builder $q) => $q->active()->orWhereIn('id', $cases->keys()->all()))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(function (NotifiableDisease $disease) use ($cases) {
                /** @var Collection<int, SurveillanceCase> $group */
                $group = $cases->get($disease->id, collect());

                return [
                    'disease' => $disease->name.($disease->is_active ? '' : ' (retired)'),
                    'category' => $disease->category->label(),
                    'cases' => (string) $group->count(),
                    'confirmed' => (string) $group->where('classification.value', 'confirmed')->count(),
                    'deaths' => (string) $group->where('outcome', CaseOutcome::Dead)->count(),
                    'pending' => (string) $group->where('notification_status', CaseNotificationStatus::Pending)->count(),
                ];
            })
            ->all();

        $all = $cases->flatten(1);

        return [
            'columns' => [
                ['key' => 'disease', 'label' => 'Disease', 'align' => 'left'],
                ['key' => 'category', 'label' => 'Category', 'align' => 'left'],
                ['key' => 'cases', 'label' => 'Cases', 'align' => 'right'],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'align' => 'right'],
                ['key' => 'deaths', 'label' => 'Deaths', 'align' => 'right'],
                ['key' => 'pending', 'label' => 'Awaiting notification', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Cases', 'value' => (string) $all->count()],
                ['label' => 'Deaths', 'value' => (string) $all->where('outcome', CaseOutcome::Dead)->count()],
            ],
        ];
    }

    /**
     * How the case stands against its notification deadline, for the line list.
     */
    private function timeliness(SurveillanceCase $case): string
    {
        return match ($case->notificationPhase()) {
            SurveillanceCase::PHASE_NOTIFIED => 'On time',
            SurveillanceCase::PHASE_NOTIFIED_LATE => 'Late',
            SurveillanceCase::PHASE_OVERDUE => 'Overdue',
            SurveillanceCase::PHASE_DUE => 'Due',
            default => '—',
        };
    }

    /**
     * @return Collection<int, SurveillanceCase>
     */
    private function casesIn(Carbon $from, Carbon $to): Collection
    {
        return SurveillanceCase::query()
            ->open()
            ->whereBetween('detected_at', [$from, $to])
            ->with(['disease:id,name', 'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth,phone'])
            ->orderBy('detected_at')
            ->get();
    }
}

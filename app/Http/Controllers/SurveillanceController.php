<?php

namespace App\Http\Controllers;

use App\Enums\CaseClassification;
use App\Enums\CaseNotificationStatus;
use App\Enums\CaseOutcome;
use App\Http\Requests\NotifySurveillanceCaseRequest;
use App\Http\Requests\UpdateSurveillanceCaseRequest;
use App\Models\NotifiableDisease;
use App\Models\Patient;
use App\Models\SurveillanceCase;
use App\Services\CaseSurveillance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The IDSR case register: cases opened from coded diagnoses, their
 * classification and outcome, and the notification to the LGA DSNO.
 */
class SurveillanceController extends Controller
{
    public function __construct(private readonly CaseSurveillance $surveillance) {}

    /**
     * The register, newest first, with what still needs notifying on top.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'disease' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = [
            'status' => (string) $request->string('status'),
            'classification' => (string) $request->string('classification'),
            'disease' => $request->integer('disease') ?: null,
            'search' => trim((string) $request->string('search')),
            'from' => (string) $request->string('from'),
            'to' => (string) $request->string('to'),
        ];

        $cases = SurveillanceCase::query()
            ->with(['disease:id,name', 'patient:id,file_number,surname,first_name,other_names,sex,date_of_birth,lga'])
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('notification_status', $filters['status']))
            ->when($filters['classification'] !== '', fn (Builder $q) => $q->where('classification', $filters['classification']))
            ->when($filters['classification'] === '', fn (Builder $q) => $q->open())
            ->when($filters['disease'], fn (Builder $q) => $q->where('notifiable_disease_id', $filters['disease']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $term = "%{$filters['search']}%";

                $query->whereIn('patient_id', Patient::query()
                    ->select('id')
                    ->where(fn (Builder $q) => $q
                        ->where('file_number', 'like', $term)
                        ->orWhere('surname', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('other_names', 'like', $term)));
            })
            ->when($filters['from'] !== '', fn (Builder $q) => $q->where('detected_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->where('detected_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            // Pending notifications first, then most recent.
            ->orderByRaw("case when notification_status = 'pending' then 0 else 1 end")
            ->orderByDesc('detected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (SurveillanceCase $case) => $this->row($case));

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        return Inertia::render('surveillance/Index', [
            'cases' => $cases,
            'filters' => $filters,
            'summary' => [
                'pending' => SurveillanceCase::query()->awaitingNotification()->count(),
                'overdue' => SurveillanceCase::query()->overdue()->count(),
                'this_week' => SurveillanceCase::query()->open()->where('detected_at', '>=', $weekStart)->count(),
                'week_label' => $weekStart->isoFormat('D MMM').' – '.$weekStart->copy()->endOfWeek(Carbon::SUNDAY)->isoFormat('D MMM'),
            ],
            'diseases' => NotifiableDisease::query()->active()->orderBy('sort_order')->get(['id', 'name']),
            'statuses' => collect(CaseNotificationStatus::cases())->map(fn (CaseNotificationStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
            'classifications' => CaseClassification::options(),
        ]);
    }

    /**
     * One case: the patient, the diagnosis that opened it, its status and
     * the notification record.
     */
    public function show(SurveillanceCase $case): Response
    {
        $case->load(['disease', 'patient', 'encounter:id,type', 'source', 'detectedBy:id,name', 'classifiedBy:id,name', 'notifiedBy:id,name']);
        $patient = $case->patient;
        $diagnosis = $case->diagnosis();

        return Inertia::render('surveillance/Show', [
            'case' => [
                ...$this->row($case),
                'case_definition' => $case->case_definition,
                'instruction' => $case->category->instruction(),
                'requires_contact_tracing' => $case->requires_contact_tracing,
                'outcome' => $case->outcome->value,
                'onset_date' => $case->onset_date?->toDateString(),
                'notes' => $case->notes,
                'detected_by' => $case->detectedBy?->name,
                'classified_at' => $case->classified_at?->isoFormat('D MMM YYYY, HH:mm'),
                'classified_by' => $case->classifiedBy?->name,
                'notification_due_at' => $case->notification_due_at?->isoFormat('D MMM YYYY, HH:mm'),
                'notified_at' => $case->notified_at?->isoFormat('D MMM YYYY, HH:mm'),
                'notified_by' => $case->notifiedBy?->name,
                'notified_to' => $case->notified_to,
                'notification_reference' => $case->notification_reference,
                'problem' => $diagnosis ? ['name' => $diagnosis->name, 'code' => $diagnosis->code] : null,
                'encounter_url' => $case->encounter ? route('encounters.show', $case->encounter) : null,
                'patient_url' => route('patients.show', $patient),
                'patient_details' => [
                    'phone' => $patient->phone,
                    'address' => $patient->address,
                    'lga' => $patient->lga,
                    'state' => $patient->state,
                    'next_of_kin' => $patient->next_of_kin_name,
                    'next_of_kin_phone' => $patient->next_of_kin_phone,
                ],
            ],
            // Only the transitions the lifecycle allows from where the case is.
            'classifications' => $case->classification->availableOptions(),
            'outcomes' => CaseOutcome::options(),
        ]);
    }

    /**
     * Move the case along its lifecycle and record onset, outcome and notes.
     */
    public function update(UpdateSurveillanceCaseRequest $request, SurveillanceCase $case): RedirectResponse
    {
        $data = $request->validated();

        $this->surveillance->classify($case, CaseClassification::from($data['classification']), $request->user());

        $case->update([
            'outcome' => $data['outcome'],
            'onset_date' => $data['onset_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Case updated.']);

        return back();
    }

    /**
     * Record that the DSNO has been notified.
     */
    public function notify(NotifySurveillanceCaseRequest $request, SurveillanceCase $case): RedirectResponse
    {
        $data = $request->validated();

        $case->update([
            'notification_status' => CaseNotificationStatus::Notified,
            'notified_at' => isset($data['notified_at']) ? Carbon::parse($data['notified_at']) : now(),
            'notified_by' => $request->user()->id,
            'notified_to' => $data['notified_to'],
            'notification_reference' => $data['notification_reference'] ?? null,
            'notes' => isset($data['notes']) && trim($data['notes']) !== ''
                ? trim(($case->notes ?? '')."\n".$data['notes'])
                : $case->notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Notification recorded for {$case->disease->name}."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(SurveillanceCase $case): array
    {
        $patient = $case->patient;

        return [
            'id' => $case->id,
            'detected_at' => $case->detected_at->isoFormat('D MMM YYYY, HH:mm'),
            'disease' => $case->disease->name,
            'category' => $case->category->value,
            'category_label' => $case->category->label(),
            'icd_code' => $case->icd_code,
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->fullName(),
                'file_number' => $patient->file_number,
                'sex' => $patient->sex,
                'age' => $patient->age(),
                'lga' => $case->residence_lga ?? $patient->lga,
            ],
            'classification' => $case->classification->value,
            'classification_label' => $case->classification->label(),
            'classification_tone' => $case->classification->tone(),
            'outcome_label' => $case->outcome->label(),
            'notification_status' => $case->notification_status->value,
            'notification_label' => $case->notification_status->label(),
            'notification_tone' => $case->notification_status->tone(),
            'phase' => $case->notificationPhase(),
            'overdue' => $case->isOverdue(),
            'href' => route('surveillance.show', $case),
        ];
    }
}

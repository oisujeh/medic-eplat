<?php

namespace App\Services;

use App\Enums\CaseClassification;
use App\Enums\CaseNotificationStatus;
use App\Models\Encounter;
use App\Models\NotifiableDisease;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Opens and maintains IDSR cases.
 *
 * open() is the one way a case comes into being, whatever detected it: the
 * diagnosis screening below today, maternity and immunization events later.
 * Every change goes through Eloquent so the audit trail records it, including
 * the unlinking of a source record that is being deleted.
 */
class CaseSurveillance
{
    /**
     * The active diagnosis-detected disease a code identifies, if any.
     */
    public function match(?string $code): ?NotifiableDisease
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return $this->diagnosisDiseases()->first(fn (NotifiableDisease $disease) => $disease->matches($code));
    }

    /**
     * Open a case, or return the one already opened by the same source.
     *
     * The catalogue rules (category, case definition, notification deadline,
     * contact tracing) and the patient's residence are copied onto the case
     * so it reads the same however the catalogue or folder change later.
     *
     * @param  array<string, mixed>  $attributes  Optional onset_date, icd_code, notes.
     */
    public function open(
        NotifiableDisease $disease,
        Patient $patient,
        ?Model $source = null,
        ?Encounter $encounter = null,
        ?User $actor = null,
        array $attributes = [],
    ): SurveillanceCase {
        if ($source !== null) {
            $existing = SurveillanceCase::query()->whereMorphedTo('source', $source)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $detectedAt = now();

        return SurveillanceCase::create([
            'notifiable_disease_id' => $disease->id,
            'patient_id' => $patient->id,
            'encounter_id' => $encounter?->id,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'icd_code' => $attributes['icd_code'] ?? null,
            ...$this->ruleSnapshot($disease, $detectedAt),
            'classification' => CaseClassification::Suspected,
            'classified_at' => $detectedAt,
            'classified_by' => $actor?->id,
            'onset_date' => $attributes['onset_date'] ?? null,
            'detected_at' => $detectedAt,
            'detected_by' => $actor?->id,
            'notification_status' => $this->initialStatus($disease),
            'residence_state' => $patient->state,
            'residence_lga' => $patient->lga,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    /**
     * Reconcile a coded diagnosis with the register and return its case.
     *
     * The returned case's wasRecentlyCreated flag tells the caller whether
     * this write is what detected it, so the clinician can be alerted once.
     */
    public function screen(Problem $problem, ?User $actor = null): ?SurveillanceCase
    {
        $disease = $this->match($problem->code);
        $case = SurveillanceCase::query()->whereMorphedTo('source', $problem)->first();

        if ($disease === null) {
            if ($case !== null && $case->classification->isOpen() && $case->notified_at === null) {
                $this->discard($case, 'Diagnosis re-coded to a condition that is not notifiable.');
            }

            return null;
        }

        if ($case === null) {
            $encounter = $problem->encounter;

            return $this->open(
                $disease,
                $problem->patient,
                $problem,
                $encounter instanceof Encounter ? $encounter : null,
                $actor,
                ['icd_code' => $problem->code, 'onset_date' => $problem->onset_date],
            );
        }

        $changes = [];

        if ($case->notifiable_disease_id !== $disease->id) {
            $changes = ['notifiable_disease_id' => $disease->id, ...$this->ruleSnapshot($disease, $case->detected_at)];

            if ($case->notified_at === null) {
                $changes['notification_status'] = $this->initialStatus($disease);
            }
        }

        if ($case->icd_code !== $problem->code) {
            $changes['icd_code'] = $problem->code;
        }

        // A diagnosis coded back to a notifiable disease reopens its case.
        if (! $case->classification->isOpen()) {
            $changes += ['classification' => CaseClassification::Suspected, 'classified_at' => now(), 'classified_by' => $actor?->id];
        }

        if ($changes !== []) {
            $case->update($changes);
        }

        return $case;
    }

    /**
     * A source record is being deleted: unlink it from its case, and drop
     * the case unless the DSNO was already notified.
     */
    public function forget(Model $source): void
    {
        $case = SurveillanceCase::query()->whereMorphedTo('source', $source)->first();

        if ($case === null) {
            return;
        }

        $case->update([
            'source_type' => null,
            'source_id' => null,
            'notes' => $this->appendNote($case, 'Source record removed: '.class_basename($source).' #'.$source->getKey().'.'),
        ]);

        if ($case->classification->isOpen() && $case->notified_at === null) {
            $this->discard($case, 'Diagnosis removed from the problem list.');
        }
    }

    /**
     * Move a case along its lifecycle, refusing transitions the lifecycle
     * does not allow.
     *
     * @throws ValidationException
     */
    public function classify(SurveillanceCase $case, CaseClassification $to, ?User $actor = null): void
    {
        if ($to === $case->classification) {
            return;
        }

        if (! $case->classification->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'classification' => "A {$case->classification->label()} case cannot be reclassified as {$to->label()}.",
            ]);
        }

        $case->update([
            'classification' => $to,
            'classified_at' => now(),
            'classified_by' => $actor?->id,
        ]);
    }

    /**
     * The open cases to flag on a patient's chart, most recent first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bannerFor(Patient $patient): array
    {
        return SurveillanceCase::query()
            ->where('patient_id', $patient->id)
            ->open()
            ->with('disease:id,name')
            ->latest('detected_at')
            ->take(5)
            ->get()
            ->map(fn (SurveillanceCase $case) => [
                'id' => $case->id,
                'disease' => $case->disease->name,
                'category' => $case->category->value,
                'category_label' => $case->category->label(),
                'instruction' => $case->category->instruction(),
                'classification' => $case->classification->value,
                'classification_label' => $case->classification->label(),
                'notification_status' => $case->notification_status->value,
                'notification_label' => $case->notification_status->label(),
                'detected_at' => $case->detected_at->isoFormat('D MMM YYYY, HH:mm'),
                'href' => route('surveillance.show', $case),
            ])
            ->values()
            ->all();
    }

    /**
     * Immediately notifiable cases the DSNO has not yet been told about.
     */
    public function pendingNotifications(): int
    {
        return SurveillanceCase::query()->awaitingNotification()->count();
    }

    /**
     * The catalogue rules copied onto a case.
     *
     * @return array<string, mixed>
     */
    private function ruleSnapshot(NotifiableDisease $disease, \DateTimeInterface $detectedAt): array
    {
        return [
            'category' => $disease->category,
            'case_definition' => $disease->case_definition,
            'requires_contact_tracing' => (bool) $disease->requires_contact_tracing,
            'notification_due_at' => $disease->notification_hours !== null
                ? Carbon::instance($detectedAt)->addHours($disease->notification_hours)
                : null,
        ];
    }

    private function initialStatus(NotifiableDisease $disease): CaseNotificationStatus
    {
        return $disease->notification_hours !== null
            ? CaseNotificationStatus::Pending
            : CaseNotificationStatus::Weekly;
    }

    private function discard(SurveillanceCase $case, string $reason): void
    {
        $case->update([
            'classification' => CaseClassification::Discarded,
            'classified_at' => now(),
            'classified_by' => auth()->id(),
            'notes' => $this->appendNote($case, $reason),
        ]);
    }

    private function appendNote(SurveillanceCase $case, string $line): string
    {
        return trim(($case->notes ?? '')."\n".$line);
    }

    /**
     * The catalogue entries that detect from diagnoses, read fresh each time:
     * a service instance can outlive a request (Octane, the test runner), and
     * a deactivated disease must stop detecting at once.
     *
     * @return Collection<int, NotifiableDisease>
     */
    private function diagnosisDiseases(): Collection
    {
        return NotifiableDisease::query()
            ->active()
            ->detectedByDiagnosis()
            ->orderBy('sort_order')
            ->get();
    }
}

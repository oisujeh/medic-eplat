<?php

namespace App\Services;

use App\Enums\FormEntryMode;
use App\Enums\FormSubmissionStatus;
use App\Events\FormCompleted;
use App\Models\Encounter;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Forms\FormEvaluator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Filling forms: opening one against an encounter, saving as the user
 * types (PRD FRM-08), completing it into the value table, and voiding it
 * with a reason.
 */
class FormSubmissions
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly DomainEvents $events,
    ) {}

    /**
     * The submission of a form on an encounter: the one already started,
     * or a fresh draft at the form's current version.
     *
     * @throws ValidationException when the form is not offered
     */
    public function open(Form $form, Encounter $encounter, User $actor): FormSubmission
    {
        $existing = FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('encounter_id', $encounter->id)
            ->live()
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $version = $form->status->isOffered() ? $form->currentVersion : null;

        if ($version === null) {
            throw ValidationException::withMessages(['form' => "{$form->name} is not currently offered."]);
        }

        return FormSubmission::create([
            'form_id' => $form->id,
            'form_version_id' => $version->id,
            'patient_id' => $encounter->patient_id,
            'visit_id' => $encounter->visit_id,
            'encounter_id' => $encounter->id,
            'status' => FormSubmissionStatus::Draft,
            'entry_mode' => $form->entry_mode,
            'data' => $this->defaults($version->parsed()->fields()),
            'filled_by' => $actor->id,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Keep what has been typed so far. Answers are normalised and the
     * calculated fields refreshed, but nothing is required yet.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(FormSubmission $submission, array $data, User $actor, ?FormEntryMode $entryMode = null): FormSubmission
    {
        $this->assertEditable($submission);

        $evaluator = $this->evaluator($submission, $data);

        $submission->update([
            'data' => $evaluator->data(),
            'entry_mode' => $entryMode ?? $submission->entry_mode,
            'last_saved_at' => Carbon::now(),
        ]);

        return $submission;
    }

    /**
     * Complete the form: every visible answer must be valid, and each is
     * then written to the value table. Completing again after an edit
     * rewrites the rows.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException listing what is wrong, keyed "data.<path>"
     */
    public function complete(FormSubmission $submission, array $data, User $actor, ?FormEntryMode $entryMode = null): FormSubmission
    {
        $this->assertEditable($submission);

        $evaluator = $this->evaluator($submission, $data);
        $errors = $evaluator->errors();

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn (string $message, string $path) => ["data.{$path}" => $message])->all(),
            );
        }

        return DB::transaction(function () use ($submission, $actor, $entryMode, $evaluator) {
            $now = Carbon::now();
            $recordedAt = $submission->encounter?->clinicalDate() ?? $now;

            $submission->update([
                'data' => $evaluator->data(),
                'entry_mode' => $entryMode ?? $submission->entry_mode,
                'status' => FormSubmissionStatus::Completed,
                'completed_by' => $actor->id,
                'completed_at' => $now,
                'last_saved_at' => $now,
            ]);

            $submission->values()->delete();

            foreach ($evaluator->rows() as $row) {
                $submission->values()->create([
                    ...$row,
                    'form_id' => $submission->form_id,
                    'form_version_id' => $submission->form_version_id,
                    'patient_id' => $submission->patient_id,
                    'encounter_id' => $submission->encounter_id,
                    'recorded_at' => $recordedAt,
                    'created_at' => $now,
                ]);
            }

            $this->events->publish(new FormCompleted($submission, $actor));

            return $submission->refresh();
        });
    }

    /**
     * Strike the submission out. Its answers stay on the record for the
     * audit trail, but leave the value table.
     */
    public function void(FormSubmission $submission, string $reason, User $actor): FormSubmission
    {
        $this->assertEditable($submission);

        return DB::transaction(fn () => $this->audit->withReason($reason, function () use ($submission, $reason, $actor) {
            $submission->update([
                'status' => FormSubmissionStatus::Voided,
                'voided_by' => $actor->id,
                'voided_at' => Carbon::now(),
                'void_reason' => $reason,
            ]);

            $submission->values()->delete();

            return $submission;
        }));
    }

    /**
     * The submission's answers run through its own form version.
     *
     * @param  array<string, mixed>  $data
     */
    public function evaluator(FormSubmission $submission, array $data): FormEvaluator
    {
        $submission->loadMissing('version', 'patient', 'encounter');

        return new FormEvaluator($submission->version->parsed(), $data, $this->context($submission));
    }

    /**
     * What a form may read about the patient it is filled for.
     *
     * @return array<string, mixed>
     */
    public function context(FormSubmission $submission): array
    {
        $patient = $submission->patient;
        $encounterAt = $submission->encounter?->clinicalDate() ?? $submission->started_at;

        return [
            'age' => $patient?->age(),
            'age_days' => $patient?->date_of_birth?->diffInDays(Carbon::today()),
            'sex' => $patient?->sex,
            'encounter_at' => $encounterAt->toIso8601String(),
            'today' => Carbon::today()->toDateString(),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function assertEditable(FormSubmission $submission): void
    {
        if (! $submission->status->isEditable()) {
            throw ValidationException::withMessages(['status' => 'A voided form cannot be changed.']);
        }
    }

    /**
     * The answers a fresh submission starts with: each field's declared
     * default. Repeating sections start empty.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function defaults(array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($field['_repeating']) {
                $data[$field['_section']] ??= [];

                continue;
            }

            if (array_key_exists('default', $field)) {
                $data[$field['key']] = $field['default'];
            }
        }

        return $data;
    }
}

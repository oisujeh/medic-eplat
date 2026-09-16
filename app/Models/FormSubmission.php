<?php

namespace App\Models;

use App\Enums\FormEntryMode;
use App\Enums\FormSubmissionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One filled form: the answers a member of staff entered for a patient,
 * against the form version in force when it was started.
 *
 * @property int $id
 * @property string $ulid
 * @property int $form_id
 * @property int $form_version_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property FormSubmissionStatus $status
 * @property FormEntryMode $entry_mode
 * @property array<string, mixed>|null $data
 * @property int|null $filled_by
 * @property Carbon $started_at
 * @property Carbon|null $last_saved_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 */
#[Fillable([
    'form_id', 'form_version_id', 'patient_id', 'visit_id', 'encounter_id', 'status', 'entry_mode',
    'data', 'filled_by', 'started_at', 'last_saved_at', 'completed_by', 'completed_at',
    'voided_by', 'voided_at', 'void_reason',
])]
class FormSubmission extends Model implements AuditableRecord
{
    use Auditable, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormSubmissionStatus::class,
            'entry_mode' => FormEntryMode::class,
            'data' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Submissions that still count: drafts and completed ones.
     *
     * @param  Builder<FormSubmission>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', '!=', FormSubmissionStatus::Voided->value);
    }

    /**
     * @param  Builder<FormSubmission>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', FormSubmissionStatus::Completed->value);
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * The flattened answers, written when the submission is completed.
     *
     * @return HasMany<FormValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(FormValue::class);
    }

    public function isDraft(): bool
    {
        return $this->status === FormSubmissionStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === FormSubmissionStatus::Completed;
    }

    public function isVoided(): bool
    {
        return $this->status === FormSubmissionStatus::Voided;
    }

    public function auditLabel(): string
    {
        return "Form submission #{$this->id}";
    }
}

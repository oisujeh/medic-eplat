<?php

namespace App\Models;

use App\Enums\CaseClassification;
use App\Enums\CaseNotificationStatus;
use App\Enums\CaseOutcome;
use App\Enums\NotifiableDiseaseCategory;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A notifiable-disease case, opened from a source record and carrying a
 * snapshot of the catalogue rules that applied when it was detected.
 *
 * @property int $id
 * @property int $notifiable_disease_id
 * @property int $patient_id
 * @property int|null $encounter_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $icd_code
 * @property NotifiableDiseaseCategory $category
 * @property string|null $case_definition
 * @property bool $requires_contact_tracing
 * @property Carbon|null $notification_due_at
 * @property CaseClassification $classification
 * @property Carbon|null $classified_at
 * @property int|null $classified_by
 * @property Carbon|null $onset_date
 * @property CaseOutcome $outcome
 * @property Carbon $detected_at
 * @property int|null $detected_by
 * @property CaseNotificationStatus $notification_status
 * @property Carbon|null $notified_at
 * @property int|null $notified_by
 * @property string|null $notified_to
 * @property string|null $notification_reference
 * @property string|null $residence_state
 * @property string|null $residence_lga
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'notifiable_disease_id', 'patient_id', 'encounter_id', 'source_type', 'source_id', 'icd_code',
    'category', 'case_definition', 'requires_contact_tracing', 'notification_due_at',
    'classification', 'classified_at', 'classified_by', 'onset_date', 'outcome',
    'detected_at', 'detected_by',
    'notification_status', 'notified_at', 'notified_by', 'notified_to', 'notification_reference',
    'residence_state', 'residence_lga', 'notes',
])]
class SurveillanceCase extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Where a case stands against its notification deadline. "escalated" is
     * reserved for a future escalation workflow.
     */
    public const PHASE_NOT_REQUIRED = 'not_required';

    public const PHASE_DUE = 'due';

    public const PHASE_OVERDUE = 'overdue';

    public const PHASE_NOTIFIED = 'notified';

    public const PHASE_NOTIFIED_LATE = 'notified_late';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotifiableDiseaseCategory::class,
            'requires_contact_tracing' => 'boolean',
            'notification_due_at' => 'datetime',
            'classification' => CaseClassification::class,
            'classified_at' => 'datetime',
            'outcome' => CaseOutcome::class,
            'notification_status' => CaseNotificationStatus::class,
            'onset_date' => 'date',
            'detected_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Cases that still count: everything not discarded.
     *
     * @param  Builder<SurveillanceCase>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('classification', '!=', CaseClassification::Discarded->value);
    }

    /**
     * Immediately notifiable cases the DSNO has not been told about.
     *
     * @param  Builder<SurveillanceCase>  $query
     */
    #[Scope]
    protected function awaitingNotification(Builder $query): void
    {
        $query->open()->where('notification_status', CaseNotificationStatus::Pending->value);
    }

    /**
     * Awaiting notification and past the deadline.
     *
     * @param  Builder<SurveillanceCase>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->awaitingNotification()->where('notification_due_at', '<', now());
    }

    /**
     * @return BelongsTo<NotifiableDisease, $this>
     */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(NotifiableDisease::class, 'notifiable_disease_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * The record that opened the case: a Problem today, other modules'
     * records later.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The coded diagnosis behind the case, when that is what opened it.
     */
    public function diagnosis(): ?Problem
    {
        $source = $this->source;

        return $source instanceof Problem ? $source : null;
    }

    /**
     * Laboratory requisitions raised in the encounter that opened the case.
     *
     * Derived through the encounter for now; explicit specimen-to-case
     * linking will replace this with a pivot when the lab workflow is built.
     *
     * @return HasMany<LabOrder, $this>
     */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class, 'encounter_id', 'encounter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function detectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_by');
    }

    /**
     * Where the case stands against its notification deadline.
     */
    public function notificationPhase(): string
    {
        if ($this->notification_due_at === null) {
            return $this->notified_at ? self::PHASE_NOTIFIED : self::PHASE_NOT_REQUIRED;
        }

        if ($this->notified_at !== null) {
            return $this->notified_at->lte($this->notification_due_at) ? self::PHASE_NOTIFIED : self::PHASE_NOTIFIED_LATE;
        }

        return $this->notification_due_at->isPast() ? self::PHASE_OVERDUE : self::PHASE_DUE;
    }

    public function isOverdue(): bool
    {
        return $this->notificationPhase() === self::PHASE_OVERDUE;
    }

    public function auditLabel(): string
    {
        return 'Surveillance case #'.$this->getKey().' ('.$this->disease->name.')';
    }
}

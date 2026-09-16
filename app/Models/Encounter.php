<?php

namespace App\Models;

use App\Enums\EncounterOutcome;
use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Carbon\CarbonInterface;
use Database\Factories\EncounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A documented clinical contact with a patient: a consultation, triage or
 * nursing session, ward round or discharge. Every clinical record made during
 * the contact links back to it.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $visit_id
 * @property int|null $queue_entry_id
 * @property int|null $admission_id
 * @property int|null $service_point_id
 * @property int|null $author_id
 * @property EncounterType $type
 * @property EncounterStatus $status
 * @property string|null $presenting_complaint
 * @property string|null $subjective
 * @property string|null $objective
 * @property string|null $assessment
 * @property string|null $plan
 * @property array<string, mixed>|null $structured
 * @property EncounterOutcome|null $outcome
 * @property Carbon|null $follow_up_at
 * @property Carbon|null $started_at
 * @property Carbon|null $signed_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'visit_id', 'queue_entry_id', 'admission_id', 'service_point_id', 'author_id',
    'entered_by', 'entered_at', 'encounter_at',
    'type', 'status',
    'presenting_complaint', 'subjective', 'objective', 'assessment', 'plan', 'structured',
    'outcome', 'follow_up_at', 'started_at', 'signed_at',
])]
class Encounter extends Model implements AuditableRecord
{
    /** @use HasFactory<EncounterFactory> */
    use Auditable, HasFactory, HasPublicId;

    /**
     * The narrative fields a clinician documents.
     *
     * @var array<int, string>
     */
    public const NARRATIVE = ['presenting_complaint', 'subjective', 'objective', 'assessment', 'plan', 'structured'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EncounterType::class,
            'status' => EncounterStatus::class,
            'outcome' => EncounterOutcome::class,
            'structured' => 'array',
            'follow_up_at' => 'datetime',
            'started_at' => 'datetime',
            'signed_at' => 'datetime',
            'entered_at' => 'datetime',
            'encounter_at' => 'datetime',
        ];
    }

    /**
     * The clinical date of the contact: the backdated date when one was
     * given, otherwise when the record was opened.
     */
    public function clinicalDate(): ?CarbonInterface
    {
        return $this->encounter_at ?? $this->started_at;
    }

    /**
     * Whether the note was entered on a later day than the contact it
     * documents (retrospective entry from paper).
     */
    public function isBackdated(): bool
    {
        return $this->encounter_at !== null
            && $this->started_at !== null
            && $this->encounter_at->toDateString() < $this->started_at->toDateString();
    }

    /**
     * The member of staff who typed the note, when not the provider.
     *
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
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
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * The clinician or nurse documenting the encounter.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Problems recorded during the encounter.
     *
     * @return HasMany<Problem, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class)->latest('referred_at');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }

    /**
     * The primary and secondary diagnoses recorded at this encounter.
     *
     * @return HasMany<Problem, $this>
     */
    public function codedDiagnoses(): HasMany
    {
        return $this->hasMany(Problem::class)
            ->whereIn('role', [Problem::ROLE_PRIMARY, Problem::ROLE_SECONDARY])
            ->orderByRaw("case when role = 'primary' then 0 else 1 end")
            ->orderBy('id');
    }

    /**
     * @return HasMany<Medication, $this>
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    /**
     * @return HasMany<LabOrder, $this>
     */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    /**
     * @return HasMany<Immunization, $this>
     */
    public function immunizations(): HasMany
    {
        return $this->hasMany(Immunization::class)->latest('administered_at');
    }

    /**
     * Notes appended after sign-off, oldest first.
     *
     * @return HasMany<EncounterAddendum, $this>
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(EncounterAddendum::class)->orderBy('recorded_at')->orderBy('id');
    }

    /**
     * Measurement sets taken during the encounter, newest first.
     *
     * @return HasMany<ObservationSet, $this>
     */
    public function observationSets(): HasMany
    {
        return $this->hasMany(ObservationSet::class)->latest('recorded_at')->latest('id');
    }

    /**
     * Clinical forms filled on the encounter, in the order they were started.
     *
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class)->orderBy('id');
    }

    /**
     * The forms an encounter summary lists: live submissions carrying what
     * the summary names. Eager-load this on summaries rather than
     * formSubmissions() so voided forms stay out of the history.
     *
     * @return HasMany<FormSubmission, $this>
     */
    public function summaryFormSubmissions(): HasMany
    {
        return $this->formSubmissions()
            ->live()
            ->with(['form:id,slug,name', 'version:id,version', 'filledBy:id,name', 'completedBy:id,name']);
    }

    /**
     * Scope to signed-off encounters.
     *
     * @param  Builder<Encounter>  $query
     */
    #[Scope]
    protected function signed(Builder $query): void
    {
        $query->where('status', EncounterStatus::Signed);
    }

    /**
     * Scope to encounters of one type.
     *
     * @param  Builder<Encounter>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, EncounterType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope to physician consultations.
     *
     * @param  Builder<Encounter>  $query
     */
    #[Scope]
    protected function consultations(Builder $query): void
    {
        $query->where('type', EncounterType::Consultation);
    }

    /**
     * Scope to encounters held at a service point with the given slug.
     *
     * @param  Builder<Encounter>  $query
     */
    #[Scope]
    protected function atServicePoint(Builder $query, string $slug): void
    {
        $query->whereHas('servicePoint', fn (Builder $q) => $q->where('slug', $slug));
    }

    /**
     * Whether the encounter has been signed off.
     */
    public function isSigned(): bool
    {
        return $this->status === EncounterStatus::Signed;
    }

    /**
     * Whether the encounter can still be documented.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * The diagnosis as a payer or register would read it: the coded lines
     * when any exist, otherwise the clinical impression.
     */
    public function diagnosisSummary(): ?string
    {
        $coded = $this->codedDiagnoses()->get()
            ->map(fn (Problem $p) => trim(($p->code ? $p->code.' ' : '').$p->name))
            ->filter()
            ->implode('; ');

        return $coded !== '' ? $coded : $this->assessment;
    }

    /**
     * Whether a diagnosis has been recorded, coded or as an impression.
     */
    public function hasDiagnosis(): bool
    {
        return filled($this->assessment) || $this->codedDiagnoses()->exists();
    }

    /**
     * How the encounter appears in the audit trail.
     */
    public function auditLabel(): string
    {
        return 'Encounter #'.$this->getKey().' ('.str_replace('_', ' ', $this->type->value).')';
    }
}

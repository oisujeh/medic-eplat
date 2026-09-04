<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\DischargeType;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\AdmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An inpatient episode, from the admission order to discharge.
 *
 * @property int $id
 * @property string $admission_number
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $ward_id
 * @property int|null $bed_id
 * @property AdmissionStatus $status
 * @property string $admitting_diagnosis
 * @property string|null $reason
 * @property int|null $requested_by
 * @property int|null $admitted_by
 * @property int|null $attending_id
 * @property Carbon|null $admitted_at
 * @property int|null $discharged_by
 * @property Carbon|null $discharged_at
 * @property DischargeType|null $discharge_type
 * @property string|null $discharge_summary
 * @property Carbon|null $follow_up_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
#[Fillable([
    'admission_number', 'patient_id', 'visit_id', 'encounter_id', 'ward_id', 'bed_id', 'status',
    'admitting_diagnosis', 'reason', 'requested_by', 'admitted_by', 'attending_id', 'admitted_at',
    'discharged_by', 'discharged_at', 'discharge_type', 'discharge_summary', 'follow_up_at',
    'cancelled_at', 'cancel_reason',
])]
class Admission extends Model implements AuditableRecord
{
    /** @use HasFactory<AdmissionFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'discharge_type' => DischargeType::class,
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
            'follow_up_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
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
     * The consultation that ordered the admission, when there was one.
     *
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    /**
     * The clinician responsible for the patient on the ward.
     *
     * @return BelongsTo<User, $this>
     */
    public function attending(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    /**
     * Bed placements and transfers, oldest first.
     *
     * @return HasMany<AdmissionMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(AdmissionMovement::class)->orderBy('moved_at')->orderBy('id');
    }

    /**
     * Ward documentation, most recent first.
     *
     * @return HasMany<AdmissionNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AdmissionNote::class)->latest('recorded_at')->latest('id');
    }

    /**
     * Observation sets recorded on the ward, most recent first.
     *
     * @return HasMany<ObservationSet, $this>
     */
    public function observationSets(): HasMany
    {
        return $this->hasMany(ObservationSet::class)->latest('recorded_at')->latest('id');
    }

    /**
     * Ward rounds and other encounters held during the admission, newest first.
     *
     * @return HasMany<Encounter, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->latest('started_at')->latest('id');
    }

    /**
     * Whether the patient is still an inpatient (or awaiting a bed).
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Days on the ward, counting the admission day and the discharge day.
     * Nigerian facilities bill per calendar day, so an overnight stay is two.
     */
    public function lengthOfStayDays(): ?int
    {
        if (! $this->admitted_at) {
            return null;
        }

        $end = ($this->discharged_at ?? now())->copy()->startOfDay();

        return (int) $this->admitted_at->copy()->startOfDay()->diffInDays($end) + 1;
    }

    /**
     * Scope to admissions that still hold (or await) a bed.
     *
     * @param  Builder<Admission>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [AdmissionStatus::Pending->value, AdmissionStatus::Admitted->value]);
    }
}

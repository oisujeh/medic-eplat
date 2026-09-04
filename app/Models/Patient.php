<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\PregnancyStatus;
use App\Enums\VisitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $file_number
 * @property string|null $title
 * @property string $surname
 * @property string $first_name
 * @property string|null $other_names
 * @property Carbon|null $date_of_birth
 * @property string $sex
 * @property string|null $marital_status
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string $nationality
 * @property string|null $state
 * @property string|null $lga
 * @property string|null $next_of_kin_name
 * @property string|null $next_of_kin_relationship
 * @property string|null $next_of_kin_phone
 * @property string $coverage
 * @property string|null $hmo_name
 * @property int|null $payer_id
 * @property string|null $hmo_number
 * @property string|null $hmo_plan
 * @property Carbon|null $hmo_expires_at
 * @property bool $is_transfer
 * @property string|null $transfer_from
 * @property string|null $transfer_reason
 * @property string|null $transfer_service
 * @property string $visit_category
 * @property string|null $outpatient_service
 * @property int|null $registered_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title', 'surname', 'first_name', 'other_names', 'date_of_birth', 'sex',
    'marital_status', 'phone', 'email', 'address', 'nationality', 'state', 'lga',
    'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone',
    'coverage', 'hmo_name', 'payer_id', 'hmo_number', 'hmo_plan', 'hmo_expires_at',
    'is_transfer', 'transfer_from', 'transfer_reason', 'transfer_service',
    'visit_category', 'outpatient_service', 'registered_by',
])]
class Patient extends Model implements AuditableRecord
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hmo_expires_at' => 'date',
            'is_transfer' => 'boolean',
        ];
    }

    /**
     * The records officer (user) who registered the patient.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * All of the patient's visits (encounters), most recent first.
     *
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->latest('opened_at');
    }

    /**
     * The patient's current open visit, if any.
     */
    public function openVisit(): ?Visit
    {
        return $this->visits()->where('status', VisitStatus::Open)->first();
    }

    /**
     * All observation sets for the patient, most recent first.
     *
     * @return HasMany<ObservationSet, $this>
     */
    public function observationSets(): HasMany
    {
        return $this->hasMany(ObservationSet::class)->latest('recorded_at')->latest('id');
    }

    /**
     * Individual measurements across every set, for trends.
     *
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /**
     * All clinical encounters for the patient, most recent first.
     *
     * @return HasMany<Encounter, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->latest('started_at')->latest('id');
    }

    /**
     * The patient's recorded allergies, most recent first.
     *
     * @return HasMany<Allergy, $this>
     */
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class)->latest();
    }

    /**
     * The patient's problem list, most recent first.
     *
     * @return HasMany<Problem, $this>
     */
    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class)->latest();
    }

    /**
     * The patient's medication list, most recent first.
     *
     * @return HasMany<Medication, $this>
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class)->latest();
    }

    /**
     * The patient's laboratory results, most recently resulted first.
     *
     * @return HasMany<LabResult, $this>
     */
    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class)->latest('resulted_at');
    }

    /**
     * The patient's laboratory requisitions, most recent first.
     *
     * @return HasMany<LabOrder, $this>
     */
    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class)->latest();
    }

    /**
     * The patient's bills, most recent first.
     *
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class)->latest();
    }

    /**
     * The patient's dispensing records, most recent first.
     *
     * @return HasMany<Dispense, $this>
     */
    public function dispenses(): HasMany
    {
        return $this->hasMany(Dispense::class)->latest();
    }

    /**
     * The patient's immunization records, most recently administered first.
     *
     * @return HasMany<Immunization, $this>
     */
    public function immunizations(): HasMany
    {
        return $this->hasMany(Immunization::class)->latest('administered_at');
    }

    /**
     * The patient's manual clinical alerts, most recent first.
     *
     * @return HasMany<PatientAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(PatientAlert::class)->latest();
    }

    /**
     * The patient's appointments, soonest first.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('scheduled_start');
    }

    /**
     * The patient's full name, in "Surname First Other" order.
     */
    /**
     * The HMO or scheme that pays for this patient, when covered.
     *
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * Claims raised for this patient, most recent first.
     *
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class)->latest('created_at');
    }

    /**
     * Outbound referrals, most recent first.
     *
     * @return HasMany<Referral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class)->latest('referred_at');
    }

    /**
     * Pregnancy episodes, most recent first.
     *
     * @return HasMany<Pregnancy, $this>
     */
    public function pregnancies(): HasMany
    {
        return $this->hasMany(Pregnancy::class)->latest('created_at');
    }

    /**
     * The pregnancy currently under antenatal care, if any.
     */
    public function activePregnancy(): ?Pregnancy
    {
        return $this->pregnancies()->where('status', PregnancyStatus::Active->value)->first();
    }

    /**
     * Inpatient episodes, most recent first.
     *
     * @return HasMany<Admission, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class)->latest('created_at');
    }

    /**
     * The admission the patient is currently in (or waiting on), if any.
     */
    public function activeAdmission(): ?Admission
    {
        return $this->admissions()
            ->whereIn('status', [AdmissionStatus::Pending->value, AdmissionStatus::Admitted->value])
            ->first();
    }

    public function fullName(): string
    {
        return trim(collect([$this->surname, $this->first_name, $this->other_names])
            ->filter()
            ->implode(' '));
    }

    /**
     * The patient's age in whole years, or null when no date of birth is recorded.
     */
    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /**
     * Upper-case initials for use in an avatar.
     */
    public function initials(): string
    {
        return Str::of($this->surname.' '.$this->first_name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * How the patient appears in the audit trail.
     */
    public function auditLabel(): string
    {
        return "Patient {$this->file_number} — {$this->fullName()}";
    }
}

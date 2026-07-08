<?php

namespace App\Models;

use App\Enums\VisitStatus;
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
 * @property string|null $hmo_number
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
    'coverage', 'hmo_name', 'hmo_number',
    'is_transfer', 'transfer_from', 'transfer_reason', 'transfer_service',
    'visit_category', 'outpatient_service', 'registered_by',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
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
     * All vitals/anthropometric readings for the patient, most recent first.
     *
     * @return HasMany<VitalSign, $this>
     */
    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->latest('recorded_at');
    }

    /**
     * All clinical encounters for the patient, most recent first.
     *
     * @return HasMany<Encounter, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->latest();
    }

    /**
     * The patient's full name, in "Surname First Other" order.
     */
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
}

<?php

namespace App\Models;

use App\Enums\EncounterType;
use App\Enums\PregnancyStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Carbon\CarbonInterface;
use Database\Factories\PregnancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A pregnancy episode from booking to outcome.
 *
 * @property int $id
 * @property string $pregnancy_number
 * @property int $patient_id
 * @property PregnancyStatus $status
 * @property Carbon|null $lmp
 * @property Carbon|null $edd
 * @property int|null $gravida
 * @property int|null $para
 * @property Carbon|null $booking_date
 * @property int|null $booked_by
 * @property array<int, string>|null $risk_factors
 * @property string|null $notes
 * @property string|null $outcome_note
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'pregnancy_number', 'patient_id', 'status', 'lmp', 'edd', 'gravida', 'para', 'booking_date', 'booked_by',
    'risk_factors', 'notes', 'outcome_note', 'closed_at', 'closed_by',
])]
class Pregnancy extends Model implements AuditableRecord
{
    /** @use HasFactory<PregnancyFactory> */
    use Auditable, HasFactory;

    /** Days from the last menstrual period to the expected date of delivery. */
    public const GESTATION_DAYS = 280;

    /**
     * Risk factors offered at booking, in the wording of the ANC register.
     *
     * @var array<int, string>
     */
    public const RISK_FACTORS = [
        'Previous caesarean section',
        'Previous stillbirth or neonatal death',
        'Previous postpartum haemorrhage',
        'Grand multipara (5+ births)',
        'Age under 18',
        'Age over 35',
        'Multiple pregnancy',
        'Hypertension',
        'Diabetes',
        'Anaemia',
        'Sickle cell disease',
        'HIV positive',
        'Hepatitis B positive',
        'Rhesus negative',
        'Malpresentation',
        'Height under 150 cm',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PregnancyStatus::class,
            'lmp' => 'date',
            'edd' => 'date',
            'booking_date' => 'date',
            'gravida' => 'integer',
            'para' => 'integer',
            'risk_factors' => 'array',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasOne<Delivery, $this>
     */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * The mother's signed antenatal encounters. Constrain by date to scope
     * them to this pregnancy (see ancVisitsSinceBooking()).
     *
     * @return HasMany<Encounter, $this>
     */
    public function ancVisits(): HasMany
    {
        return $this->hasMany(Encounter::class, 'patient_id', 'patient_id')
            ->ofType(EncounterType::Nursing)
            ->atServicePoint('anc')
            ->signed()
            ->latest('signed_at');
    }

    /**
     * ANC visits that belong to this pregnancy: those signed after it was
     * booked.
     *
     * @return Builder<Encounter>
     */
    public function ancVisitsSinceBooking(): Builder
    {
        return Encounter::query()
            ->where('patient_id', $this->patient_id)
            ->ofType(EncounterType::Nursing)
            ->atServicePoint('anc')
            ->signed()
            ->when($this->created_at, fn ($q) => $q->where('signed_at', '>=', $this->created_at))
            ->latest('signed_at');
    }

    /**
     * Whether the pregnancy is still being followed antenatally.
     */
    public function isActive(): bool
    {
        return $this->status === PregnancyStatus::Active;
    }

    /**
     * The expected date of delivery implied by a last menstrual period.
     */
    public static function eddFromLmp(CarbonInterface $lmp): Carbon
    {
        return Carbon::instance($lmp)->addDays(self::GESTATION_DAYS);
    }

    /**
     * Completed weeks of gestation on a date, from the LMP when known and
     * otherwise from the EDD. Null when neither is recorded.
     */
    public function gestationalAgeWeeks(?CarbonInterface $at = null): ?int
    {
        $at = Carbon::instance($at ?? now())->startOfDay();

        if ($this->lmp) {
            return max(0, (int) floor($this->lmp->copy()->startOfDay()->diffInDays($at, false) / 7));
        }

        if ($this->edd) {
            $daysToGo = $at->diffInDays($this->edd->copy()->startOfDay(), false);

            return max(0, (int) floor((self::GESTATION_DAYS - $daysToGo) / 7));
        }

        return null;
    }

    /**
     * Whether the expected date of delivery has passed without an outcome.
     */
    public function isOverdue(): bool
    {
        return $this->isActive() && $this->edd !== null && $this->edd->isPast() && ! $this->edd->isToday();
    }

    /**
     * Scope to pregnancies still under antenatal care.
     *
     * @param  Builder<Pregnancy>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', PregnancyStatus::Active->value);
    }
}

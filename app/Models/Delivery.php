<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\MaternalOutcome;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The delivery that ended a pregnancy.
 *
 * @property int $id
 * @property int $pregnancy_id
 * @property int $patient_id
 * @property int|null $admission_id
 * @property Carbon $delivered_at
 * @property DeliveryMode $mode
 * @property string|null $labour_onset
 * @property int|null $gestational_age_weeks
 * @property string $place
 * @property int|null $attendant_id
 * @property array<int, string>|null $complications
 * @property int|null $blood_loss_ml
 * @property MaternalOutcome $maternal_outcome
 * @property string|null $notes
 * @property int|null $recorded_by
 */
#[Fillable([
    'pregnancy_id', 'patient_id', 'admission_id', 'delivered_at', 'mode', 'labour_onset', 'gestational_age_weeks',
    'place', 'attendant_id', 'complications', 'blood_loss_ml', 'maternal_outcome', 'notes', 'recorded_by',
])]
class Delivery extends Model implements AuditableRecord
{
    use Auditable;

    public const PLACE_FACILITY = 'facility';

    public const PLACE_BORN_BEFORE_ARRIVAL = 'born_before_arrival';

    /** @var array<string, string> */
    public const PLACES = [
        self::PLACE_FACILITY => 'In this facility',
        self::PLACE_BORN_BEFORE_ARRIVAL => 'Born before arrival',
    ];

    /** @var array<string, string> */
    public const LABOUR_ONSETS = [
        'spontaneous' => 'Spontaneous',
        'induced' => 'Induced',
        'none' => 'No labour (elective CS)',
    ];

    /**
     * Complications recorded on the delivery register.
     *
     * @var array<int, string>
     */
    public const COMPLICATIONS = [
        'Postpartum haemorrhage',
        'Antepartum haemorrhage',
        'Pre-eclampsia / eclampsia',
        'Obstructed or prolonged labour',
        'Ruptured uterus',
        'Retained placenta',
        'Perineal tear (3rd/4th degree)',
        'Sepsis',
        'Cord prolapse',
        'Shoulder dystocia',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'mode' => DeliveryMode::class,
            'maternal_outcome' => MaternalOutcome::class,
            'gestational_age_weeks' => 'integer',
            'blood_loss_ml' => 'integer',
            'complications' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pregnancy, $this>
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<Birth, $this>
     */
    public function births(): HasMany
    {
        return $this->hasMany(Birth::class)->orderBy('birth_order');
    }
}

<?php

namespace App\Models;

use App\Enums\BirthOutcome;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One baby on the birth register.
 *
 * @property int $id
 * @property int $delivery_id
 * @property int $patient_id
 * @property int|null $newborn_patient_id
 * @property int $birth_order
 * @property BirthOutcome $outcome
 * @property string $sex
 * @property int|null $weight_grams
 * @property int|null $apgar_1
 * @property int|null $apgar_5
 * @property bool $resuscitated
 * @property bool $breastfed_within_hour
 * @property bool $bcg_given
 * @property bool $opv0_given
 * @property bool $hepb0_given
 * @property string|null $condition
 * @property string|null $notes
 */
#[Fillable([
    'delivery_id', 'patient_id', 'newborn_patient_id', 'birth_order', 'outcome', 'sex', 'weight_grams',
    'apgar_1', 'apgar_5', 'resuscitated', 'breastfed_within_hour', 'bcg_given', 'opv0_given', 'hepb0_given',
    'condition', 'notes',
])]
class Birth extends Model implements AuditableRecord
{
    use Auditable;

    /** Birth weight below which a baby counts as low birth weight, in grams. */
    public const LOW_BIRTH_WEIGHT_GRAMS = 2500;

    /** @var array<string, string> */
    public const CONDITIONS = [
        'well' => 'Well',
        'referred' => 'Referred / special care',
        'deceased' => 'Died after birth',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => BirthOutcome::class,
            'birth_order' => 'integer',
            'weight_grams' => 'integer',
            'apgar_1' => 'integer',
            'apgar_5' => 'integer',
            'resuscitated' => 'boolean',
            'breastfed_within_hour' => 'boolean',
            'bcg_given' => 'boolean',
            'opv0_given' => 'boolean',
            'hepb0_given' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * The mother.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * The baby's own patient record, once registered.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function newborn(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'newborn_patient_id');
    }

    public function isLowBirthWeight(): bool
    {
        return $this->weight_grams !== null && $this->weight_grams < self::LOW_BIRTH_WEIGHT_GRAMS;
    }
}

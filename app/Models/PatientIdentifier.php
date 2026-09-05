<?php

namespace App\Models;

use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One identifier held by a patient: the index the Master Patient Index
 * searches before a new record is created. Rows mirrored from the patient
 * record (file number, NIN, phone, HMO number) are kept in step by
 * App\Services\PatientIdentifiers whenever the patient is saved; changes
 * to them are audited on the patient.
 *
 * @property int $id
 * @property int $patient_id
 * @property IdentifierType $type
 * @property string $value
 * @property string|null $issuer
 * @property Carbon|null $verified_at
 */
#[Fillable(['patient_id', 'type', 'value', 'issuer', 'verified_at'])]
class PatientIdentifier extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IdentifierType::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

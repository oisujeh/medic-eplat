<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The record of a patient's death: when, where and from what, and the
 * clinical record that carried it (a discharge, a delivery). One per patient.
 *
 * @property int $id
 * @property string $ulid
 * @property int $patient_id
 * @property Carbon $died_at
 * @property string $place
 * @property string|null $cause
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $certified_by
 * @property int|null $recorded_by
 * @property string|null $notes
 */
#[Fillable(['patient_id', 'died_at', 'place', 'cause', 'source_type', 'source_id', 'certified_by', 'recorded_by', 'notes'])]
class Death extends Model implements AuditableRecord
{
    use Auditable, HasPublicId;

    public const PLACE_FACILITY = 'facility';

    public const PLACE_COMMUNITY = 'community';

    public const PLACE_OTHER = 'other';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'died_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function certifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function auditLabel(): string
    {
        return 'Death record for patient #'.$this->patient_id;
    }
}

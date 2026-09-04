<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\ImmunizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $administered_by
 * @property string $vaccine
 * @property string|null $dose_label
 * @property string|null $batch_no
 * @property string|null $site
 * @property string|null $route
 * @property string|null $notes
 * @property Carbon|null $administered_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'visit_id', 'encounter_id', 'administered_by',
    'vaccine', 'dose_label', 'batch_no', 'site', 'route', 'notes', 'administered_at',
])]
class Immunization extends Model implements AuditableRecord
{
    /** @use HasFactory<ImmunizationFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'administered_at' => 'datetime',
        ];
    }

    /**
     * A compact human-readable label, e.g. "Penta (OPV 1)".
     */
    public function label(): string
    {
        return $this->dose_label
            ? "{$this->vaccine} ({$this->dose_label})"
            : $this->vaccine;
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The nursing encounter the vaccine was given in.
     *
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}

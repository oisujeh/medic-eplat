<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $prescribed_by
 * @property string $name
 * @property string|null $dose
 * @property string|null $frequency
 * @property string|null $route
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $stopped_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'visit_id', 'encounter_id', 'prescribed_by', 'name',
    'dose', 'frequency', 'route', 'status', 'started_at', 'stopped_at', 'notes',
])]
class Medication extends Model implements AuditableRecord
{
    /** @use HasFactory<MedicationFactory> */
    use Auditable, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'stopped_at' => 'date',
        ];
    }

    /**
     * Limit the query to medications the patient is currently taking.
     *
     * @param  Builder<Medication>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * A compact human-readable summary, e.g. "Amlodipine 5mg OD".
     */
    public function label(): string
    {
        return trim(collect([$this->name, $this->dose, $this->frequency])
            ->filter()
            ->implode(' '));
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
    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\ProblemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $encounter_id
 * @property int|null $recorded_by
 * @property string $name
 * @property string|null $code
 * @property string $status
 * @property string|null $role
 * @property Carbon|null $onset_date
 * @property Carbon|null $resolved_date
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'encounter_id', 'recorded_by', 'name', 'code', 'icd_code_id',
    'status', 'role', 'onset_date', 'resolved_date', 'notes',
])]
class Problem extends Model implements AuditableRecord
{
    /** @use HasFactory<ProblemFactory> */
    use Auditable, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CHRONIC = 'chronic';

    public const STATUS_RESOLVED = 'resolved';

    /** Diagnosis role within the current assessment (not the longitudinal status). */
    public const ROLE_PRIMARY = 'primary';

    public const ROLE_SECONDARY = 'secondary';

    public const ROLE_DIFFERENTIAL = 'differential';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onset_date' => 'date',
            'resolved_date' => 'date',
        ];
    }

    /**
     * Limit the query to problems that are still open (active or chronic).
     *
     * @param  Builder<Problem>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_CHRONIC]);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Encounter, $this>
     */
    public function icdCode(): BelongsTo
    {
        return $this->belongsTo(IcdCode::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

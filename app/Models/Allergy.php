<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\AllergyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $recorded_by
 * @property string $substance
 * @property string|null $category
 * @property string|null $reaction
 * @property string|null $severity
 * @property string $status
 * @property Carbon|null $noted_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'recorded_by', 'substance', 'category', 'reaction',
    'severity', 'status', 'noted_at', 'notes',
])]
class Allergy extends Model implements AuditableRecord
{
    /** @use HasFactory<AllergyFactory> */
    use Auditable, HasFactory;

    protected $table = 'patient_allergies';

    public const STATUS_ACTIVE = 'active';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noted_at' => 'date',
        ];
    }

    /**
     * Limit the query to allergies that are currently active.
     *
     * @param  Builder<Allergy>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

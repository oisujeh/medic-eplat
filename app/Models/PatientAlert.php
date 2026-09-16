<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\PatientAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $created_by
 * @property string $type
 * @property string $severity
 * @property string $message
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'created_by', 'type', 'severity', 'message',
    'is_active', 'expires_at',
])]
class PatientAlert extends Model implements AuditableRecord
{
    /** @use HasFactory<PatientAlertFactory> */
    use Auditable, HasFactory;

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Limit the query to alerts that are active and not expired.
     *
     * @param  Builder<PatientAlert>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property int|null $queue_entry_id
 * @property int|null $clinician_id
 * @property string|null $presenting_complaint
 * @property string|null $history
 * @property string|null $examination
 * @property string|null $diagnosis
 * @property string|null $plan
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'visit_id', 'patient_id', 'queue_entry_id', 'clinician_id',
    'presenting_complaint', 'history', 'examination', 'diagnosis', 'plan',
    'status', 'started_at', 'completed_at',
])]
class Encounter extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    /**
     * Whether the encounter has been completed (signed off).
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}

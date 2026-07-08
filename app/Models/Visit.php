<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $visit_number
 * @property int $patient_id
 * @property VisitStatus $status
 * @property string|null $reason
 * @property int|null $opened_by
 * @property int|null $closed_by
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 */
#[Fillable(['visit_number', 'patient_id', 'status', 'reason', 'opened_by', 'closed_by', 'opened_at', 'closed_at'])]
class Visit extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The patient this visit belongs to.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The user who opened the visit.
     *
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * The queue entries (routing hops) that make up this visit, oldest first.
     *
     * @return HasMany<QueueEntry, $this>
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class)->orderBy('queued_at');
    }

    /**
     * Vitals/anthropometric readings recorded during this visit.
     *
     * @return HasMany<VitalSign, $this>
     */
    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->latest('recorded_at');
    }

    /**
     * Whether the visit is still open.
     */
    public function isOpen(): bool
    {
        return $this->status === VisitStatus::Open;
    }
}

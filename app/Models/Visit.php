<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
class Visit extends Model implements AuditableRecord
{
    /** @use HasFactory<VisitFactory> */
    use Auditable, HasFactory;

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
     * Observation sets recorded during this visit, newest first.
     *
     * @return HasMany<ObservationSet, $this>
     */
    public function observationSets(): HasMany
    {
        return $this->hasMany(ObservationSet::class)->latest('recorded_at')->latest('id');
    }

    /**
     * The most recent observation set of the visit.
     *
     * @return HasOne<ObservationSet, $this>
     */
    public function latestObservationSet(): HasOne
    {
        return $this->hasOne(ObservationSet::class)->latestOfMany('recorded_at');
    }

    /**
     * Clinical encounters held during this visit, oldest first.
     *
     * @return HasMany<Encounter, $this>
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->orderBy('started_at');
    }

    /**
     * Whether the visit is still open.
     */
    public function isOpen(): bool
    {
        return $this->status === VisitStatus::Open;
    }
}

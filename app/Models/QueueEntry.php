<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property int $service_point_id
 * @property QueueStatus $status
 * @property Priority $priority
 * @property string|null $note
 * @property int|null $routed_by
 * @property int|null $assigned_to
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'visit_id', 'patient_id', 'service_point_id', 'status', 'priority', 'note',
    'routed_by', 'assigned_to', 'queued_at', 'started_at', 'completed_at',
])]
class QueueEntry extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueStatus::class,
            'priority' => Priority::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function routedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'routed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope to entries still active in a queue (waiting or in service).
     *
     * @param  Builder<QueueEntry>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [QueueStatus::Waiting, QueueStatus::InService]);
    }

    /**
     * Scope ordering a worklist: emergencies first, then longest waiting.
     *
     * @param  Builder<QueueEntry>  $query
     */
    #[Scope]
    protected function worklistOrder(Builder $query): void
    {
        $query->orderByRaw("CASE priority WHEN 'emergency' THEN 3 WHEN 'urgent' THEN 2 ELSE 1 END DESC")
            ->orderBy('queued_at');
    }
}

<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\Priority;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $provider_id
 * @property int $service_point_id
 * @property Carbon $scheduled_start
 * @property Carbon $scheduled_end
 * @property int $duration_minutes
 * @property AppointmentStatus $status
 * @property AppointmentSource $source
 * @property Priority $priority
 * @property string|null $reason
 * @property string|null $note
 * @property int|null $visit_id
 * @property int|null $queue_entry_id
 * @property int|null $encounter_id
 * @property int|null $created_by
 * @property int|null $checked_in_by
 * @property int|null $cancelled_by
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'patient_id', 'provider_id', 'service_point_id',
    'scheduled_start', 'scheduled_end', 'duration_minutes',
    'status', 'source', 'priority', 'reason', 'note',
    'visit_id', 'queue_entry_id', 'encounter_id',
    'created_by', 'checked_in_by', 'cancelled_by',
    'checked_in_at', 'cancelled_at', 'cancellation_reason',
])]
class Appointment extends Model implements AuditableRecord
{
    /** @use HasFactory<AppointmentFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'priority' => Priority::class,
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
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
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Appointments that still occupy their slot (scheduled or checked in).
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn]);
    }

    /**
     * Appointments whose scheduled window intersects the given range.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function inRange(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where('scheduled_start', '<', $to)
            ->where('scheduled_end', '>', $from);
    }
}

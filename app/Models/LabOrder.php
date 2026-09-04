<?php

namespace App\Models;

use App\Enums\LabOrderStatus;
use App\Enums\Priority;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\LabOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $accession_number
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $queue_entry_id
 * @property int|null $ordered_by
 * @property Priority $priority
 * @property LabOrderStatus $status
 * @property string|null $clinical_details
 * @property string|null $specimen_type
 * @property int|null $collected_by
 * @property Carbon|null $collected_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $cancelled_reason
 * @property Carbon|null $created_at
 */
#[Fillable([
    'accession_number', 'patient_id', 'visit_id', 'encounter_id', 'queue_entry_id',
    'ordered_by', 'priority', 'status', 'clinical_details', 'specimen_type',
    'collected_by', 'collected_at', 'received_by', 'received_at',
    'verified_by', 'verified_at', 'cancelled_reason',
])]
class LabOrder extends Model implements AuditableRecord
{
    /** @use HasFactory<LabOrderFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => LabOrderStatus::class,
            'collected_at' => 'datetime',
            'received_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Whether every result line on the order carries a value.
     */
    public function isFullyResulted(): bool
    {
        return $this->results->isNotEmpty()
            && $this->results->every(fn (LabResult $r) => filled($r->value));
    }

    /**
     * @return HasMany<LabResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class)->orderBy('id');
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
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope to orders still being worked (not released or cancelled).
     *
     * @param  Builder<LabOrder>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [
            LabOrderStatus::Ordered->value,
            LabOrderStatus::Collected->value,
            LabOrderStatus::InProgress->value,
        ]);
    }

    /**
     * Order a worklist: STAT/urgent first, then oldest first.
     *
     * @param  Builder<LabOrder>  $query
     */
    #[Scope]
    protected function worklistOrder(Builder $query): void
    {
        $query->orderByRaw("CASE priority WHEN 'emergency' THEN 3 WHEN 'urgent' THEN 2 ELSE 1 END DESC")
            ->orderBy('created_at');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $queue_entry_id
 * @property int|null $bill_id
 * @property int|null $dispensed_by
 * @property string $status
 * @property string|null $note
 * @property Carbon|null $created_at
 */
#[Fillable([
    'patient_id', 'visit_id', 'encounter_id', 'queue_entry_id', 'bill_id',
    'dispensed_by', 'status', 'note',
])]
class Dispense extends Model implements AuditableRecord
{
    use Auditable;

    public const STATUS_DISPENSED = 'dispensed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The total value of the dispensed items.
     */
    public function total(): float
    {
        return (float) $this->items->sum('total');
    }

    /**
     * @return HasMany<DispenseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DispenseItem::class);
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
    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}

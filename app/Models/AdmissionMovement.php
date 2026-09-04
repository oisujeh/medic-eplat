<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bed placement or transfer during an admission.
 *
 * @property int $id
 * @property int $admission_id
 * @property int|null $from_ward_id
 * @property int|null $from_bed_id
 * @property int|null $to_ward_id
 * @property int|null $to_bed_id
 * @property string|null $reason
 * @property int|null $moved_by
 * @property Carbon $moved_at
 */
#[Fillable(['admission_id', 'from_ward_id', 'from_bed_id', 'to_ward_id', 'to_bed_id', 'reason', 'moved_by', 'moved_at'])]
class AdmissionMovement extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function fromWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'from_ward_id');
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function toWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'to_ward_id');
    }

    /**
     * @return BelongsTo<Bed, $this>
     */
    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    /**
     * The patient is reached through the parent admission.
     */
    public function auditPatientId(): ?int
    {
        return $this->admission?->patient_id;
    }
}

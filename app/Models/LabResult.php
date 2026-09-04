<?php

namespace App\Models;

use App\Enums\ResultFlag;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\LabResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lab_order_id
 * @property int|null $lab_test_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $ordered_by
 * @property int|null $resulted_by
 * @property string $name
 * @property string|null $code
 * @property string|null $department
 * @property string|null $value
 * @property string|null $unit
 * @property string|null $reference_range
 * @property float|null $reference_low
 * @property float|null $reference_high
 * @property string|null $flag
 * @property string|null $specimen
 * @property string $status
 * @property Carbon|null $resulted_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'lab_order_id', 'lab_test_id', 'patient_id', 'visit_id', 'encounter_id',
    'ordered_by', 'resulted_by', 'name', 'code', 'department', 'value', 'unit',
    'reference_range', 'reference_low', 'reference_high', 'flag', 'specimen',
    'status', 'resulted_at', 'notes',
])]
class LabResult extends Model implements AuditableRecord
{
    /** @use HasFactory<LabResultFactory> */
    use Auditable, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESULTED = 'resulted';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_low' => 'float',
            'reference_high' => 'float',
            'resulted_at' => 'datetime',
        ];
    }

    /**
     * Derive Low/High/Normal from the reference range for a numeric value.
     * Returns null when the value isn't numeric or no range is defined — the
     * scientist then judges Critical / Abnormal / qualitative flags manually.
     */
    public function deriveFlag(): ?ResultFlag
    {
        if (! is_numeric($this->value)) {
            return null;
        }

        $value = (float) $this->value;

        if ($this->reference_low !== null && $value < $this->reference_low) {
            return ResultFlag::Low;
        }

        if ($this->reference_high !== null && $value > $this->reference_high) {
            return ResultFlag::High;
        }

        if ($this->reference_low !== null || $this->reference_high !== null) {
            return ResultFlag::Normal;
        }

        return null;
    }

    /**
     * Limit the query to results that have been reported.
     *
     * @param  Builder<LabResult>  $query
     */
    public function scopeResulted(Builder $query): void
    {
        $query->where('status', self::STATUS_RESULTED);
    }

    /**
     * The value with its unit appended, e.g. "742 cells/mm3".
     */
    public function displayValue(): string
    {
        return trim(collect([$this->value, $this->unit])->filter()->implode(' '));
    }

    /**
     * @return BelongsTo<LabOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    /**
     * @return BelongsTo<LabTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
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
    public function resultedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by');
    }
}

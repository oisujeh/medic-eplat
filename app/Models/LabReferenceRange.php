<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reference and critical limits of a test for one age band and sex
 * (PRD LAB-02). Age is held in days so neonatal bands work.
 *
 * @property int $id
 * @property int $lab_test_id
 * @property string|null $sex
 * @property int $age_min_days
 * @property int|null $age_max_days
 * @property float|null $low
 * @property float|null $high
 * @property float|null $critical_low
 * @property float|null $critical_high
 * @property string|null $text
 */
#[Fillable([
    'lab_test_id', 'sex', 'age_min_days', 'age_max_days', 'low', 'high',
    'critical_low', 'critical_high', 'text',
])]
class LabReferenceRange extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_min_days' => 'integer',
            'age_max_days' => 'integer',
            'low' => 'float',
            'high' => 'float',
            'critical_low' => 'float',
            'critical_high' => 'float',
        ];
    }

    /**
     * Whether the band covers a patient of this age (in days) and sex.
     */
    public function covers(?int $ageDays, ?string $sex): bool
    {
        if ($this->sex !== null && $this->sex !== $sex) {
            return false;
        }

        if ($ageDays === null) {
            return $this->age_min_days === 0 && $this->age_max_days === null;
        }

        return $ageDays >= $this->age_min_days
            && ($this->age_max_days === null || $ageDays <= $this->age_max_days);
    }

    /**
     * @return BelongsTo<LabTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function auditLabel(): string
    {
        return "Reference range for lab test #{$this->lab_test_id}";
    }
}

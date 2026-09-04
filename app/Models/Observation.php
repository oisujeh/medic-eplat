<?php

namespace App\Models;

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One measurement — a temperature, a weight, a fetal heart rate.
 *
 * @property int $id
 * @property int $observation_set_id
 * @property int $patient_id
 * @property ObservationCode $code
 * @property float|null $value
 * @property string|null $text_value
 * @property string|null $unit
 * @property AlertLevel|null $level
 * @property string|null $flag
 * @property Carbon $recorded_at
 */
#[Fillable(['observation_set_id', 'patient_id', 'code', 'value', 'text_value', 'unit', 'level', 'flag', 'recorded_at'])]
class Observation extends Model implements AuditableRecord
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
            'code' => ObservationCode::class,
            'value' => 'float',
            'level' => AlertLevel::class,
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ObservationSet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(ObservationSet::class, 'observation_set_id');
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The reading as recorded: numeric, or text for categorical codes.
     */
    public function reading(): float|string
    {
        return $this->code->isText() ? (string) $this->text_value : (float) $this->value;
    }

    /**
     * The reading with its unit, e.g. "37.2 °C".
     */
    public function display(): string
    {
        if ($this->code->isText()) {
            return (string) $this->text_value;
        }

        $value = rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.');
        $unit = $this->unit ?? $this->code->unit();

        return $unit && $unit !== '/10' ? "{$value} {$unit}" : $value.($unit ?? '');
    }
}

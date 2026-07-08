<?php

namespace App\Models;

use App\Support\VitalSignInterpreter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $visit_id
 * @property int $patient_id
 * @property int|null $queue_entry_id
 * @property int|null $service_point_id
 * @property int|null $recorded_by
 * @property float|null $temperature_c
 * @property int|null $systolic_bp
 * @property int|null $diastolic_bp
 * @property int|null $pulse_bpm
 * @property int|null $respiratory_rate
 * @property int|null $spo2
 * @property float|null $blood_glucose
 * @property int|null $pain_score
 * @property float|null $weight_kg
 * @property float|null $height_cm
 * @property float|null $bmi
 * @property float|null $muac_cm
 * @property float|null $head_circumference_cm
 * @property string|null $notes
 * @property Carbon|null $recorded_at
 */
#[Fillable([
    'visit_id', 'patient_id', 'queue_entry_id', 'service_point_id', 'recorded_by',
    'temperature_c', 'systolic_bp', 'diastolic_bp', 'pulse_bpm', 'respiratory_rate',
    'spo2', 'blood_glucose', 'pain_score',
    'weight_kg', 'height_cm', 'bmi', 'muac_cm', 'head_circumference_cm',
    'notes', 'recorded_at',
])]
class VitalSign extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature_c' => 'float',
            'blood_glucose' => 'float',
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'bmi' => 'float',
            'muac_cm' => 'float',
            'head_circumference_cm' => 'float',
            'recorded_at' => 'datetime',
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
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * A short "120/80" blood-pressure string, or null when not recorded.
     */
    public function bloodPressure(): ?string
    {
        return $this->systolic_bp && $this->diastolic_bp
            ? "{$this->systolic_bp}/{$this->diastolic_bp}"
            : null;
    }

    /**
     * A display-ready summary of the reading, including clinical flags.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $interpretation = VitalSignInterpreter::interpret($this);

        return [
            'id' => $this->id,
            'temperature_c' => $this->temperature_c,
            'blood_pressure' => $this->bloodPressure(),
            'systolic_bp' => $this->systolic_bp,
            'diastolic_bp' => $this->diastolic_bp,
            'pulse_bpm' => $this->pulse_bpm,
            'respiratory_rate' => $this->respiratory_rate,
            'spo2' => $this->spo2,
            'blood_glucose' => $this->blood_glucose,
            'pain_score' => $this->pain_score,
            'weight_kg' => $this->weight_kg,
            'height_cm' => $this->height_cm,
            'bmi' => $this->bmi,
            'muac_cm' => $this->muac_cm,
            'head_circumference_cm' => $this->head_circumference_cm,
            'notes' => $this->notes,
            'recorded_by' => $this->recordedBy?->name,
            'recorded_at' => $this->recorded_at?->isoFormat('D MMM YYYY, h:mm a'),
            'recorded_at_diff' => $this->recorded_at?->diffForHumans(),
            'recorded_at_short' => $this->recorded_at?->isoFormat('D MMM, HH:mm'),
            'alert_level' => $interpretation['level'],
            'flags' => $interpretation['flags'],
        ];
    }
}

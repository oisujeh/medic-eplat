<?php

namespace App\Models;

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\ObservationSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A group of measurements taken at one moment — a vitals panel, an
 * anthropometric check, an antenatal examination.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $queue_entry_id
 * @property int|null $admission_id
 * @property int|null $recorded_by
 * @property AlertLevel $alert_level
 * @property string|null $notes
 * @property Carbon $recorded_at
 */
#[Fillable([
    'patient_id', 'visit_id', 'encounter_id', 'queue_entry_id', 'admission_id', 'recorded_by',
    'alert_level', 'notes', 'recorded_at',
])]
class ObservationSet extends Model implements AuditableRecord
{
    /** @use HasFactory<ObservationSetFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_level' => AlertLevel::class,
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
     * @return BelongsTo<Encounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * @return BelongsTo<QueueEntry, $this>
     */
    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    /**
     * @return BelongsTo<Admission, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The individual readings, in entry order.
     *
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class)->orderBy('id');
    }

    /**
     * A single reading in the set, if it was taken.
     */
    public function get(ObservationCode $code): ?Observation
    {
        return $this->observations->first(fn (Observation $o) => $o->code === $code);
    }

    /**
     * The readings keyed by code, numeric or text as recorded.
     *
     * @return array<string, float|string>
     */
    public function values(): array
    {
        return $this->observations
            ->mapWithKeys(fn (Observation $o) => [$o->code->value => $o->reading()])
            ->all();
    }

    /**
     * The readings that fell outside their reference range.
     *
     * @return array<int, array{metric: string, level: string, label: string}>
     */
    public function flags(): array
    {
        return $this->observations
            ->filter(fn (Observation $o) => $o->level !== null && $o->level !== AlertLevel::Normal)
            ->map(fn (Observation $o) => [
                'metric' => $o->code->value,
                'level' => $o->level->value,
                'label' => (string) $o->flag,
            ])
            ->values()
            ->all();
    }

    /**
     * A "120/80" blood-pressure string, or null when either side is missing.
     */
    public function bloodPressure(): ?string
    {
        $s = $this->get(ObservationCode::SystolicBp)?->value;
        $d = $this->get(ObservationCode::DiastolicBp)?->value;

        return $s !== null && $d !== null ? (int) $s.'/'.(int) $d : null;
    }
}

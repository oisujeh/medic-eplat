<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer from a completed form, flattened so every obs is queryable
 * by patient and concept in plain SQL (PRD FRM-14). Rewritten whenever the
 * submission is completed again; removed when it is voided.
 *
 * Not audited on its own: the submission whose answers these are carries
 * the trail.
 *
 * @property int $id
 * @property int $form_submission_id
 * @property int $form_id
 * @property int $form_version_id
 * @property int $patient_id
 * @property int|null $encounter_id
 * @property string $field_key
 * @property int|null $concept_id
 * @property string|null $group_key
 * @property int|null $repeat_index
 * @property string $value_type
 * @property float|null $value_numeric
 * @property string|null $value_text
 * @property int|null $value_concept_id
 * @property string|null $value_reference
 * @property Carbon|null $value_date
 * @property Carbon|null $value_datetime
 * @property bool|null $value_boolean
 * @property string|null $unit
 * @property Carbon $recorded_at
 */
#[Fillable([
    'form_submission_id', 'form_id', 'form_version_id', 'patient_id', 'encounter_id',
    'field_key', 'concept_id', 'group_key', 'repeat_index', 'value_type',
    'value_numeric', 'value_text', 'value_concept_id', 'value_reference',
    'value_date', 'value_datetime', 'value_boolean', 'unit', 'recorded_at',
])]
class FormValue extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'repeat_index' => 'integer',
            'value_numeric' => 'float',
            'value_date' => 'date',
            'value_datetime' => 'datetime',
            'value_boolean' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    /**
     * @return BelongsTo<Concept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /**
     * @return BelongsTo<Concept, $this>
     */
    public function valueConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'value_concept_id');
    }

    /**
     * The answer as recorded, whichever column holds it.
     */
    public function value(): mixed
    {
        return match ($this->value_type) {
            'numeric' => $this->value_numeric,
            'coded' => $this->value_concept_id ?? $this->value_text,
            'date' => $this->value_date?->toDateString(),
            'datetime' => $this->value_datetime?->toIso8601String(),
            'boolean' => $this->value_boolean,
            default => $this->value_text,
        };
    }
}

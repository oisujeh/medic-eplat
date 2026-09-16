<?php

namespace App\Models;

use App\Enums\RelationshipType;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A link between two patients on the register, seen from `patient_id`'s
 * side: "this patient's mother is that patient".
 *
 * @property int $id
 * @property int $patient_id
 * @property int $related_patient_id
 * @property RelationshipType $type
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['patient_id', 'related_patient_id', 'type', 'notes', 'created_by'])]
class PatientRelationship extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RelationshipType::class,
        ];
    }

    /**
     * Link two patients, writing both directions so either chart shows it.
     *
     * @return array{0: self, 1: self}
     */
    public static function link(Patient $patient, Patient $related, RelationshipType $type, ?User $actor = null, ?string $notes = null): array
    {
        return DB::transaction(function () use ($patient, $related, $type, $actor, $notes) {
            $forward = self::query()->firstOrCreate(
                ['patient_id' => $patient->id, 'related_patient_id' => $related->id, 'type' => $type->value],
                ['notes' => $notes, 'created_by' => $actor?->id],
            );

            $backward = self::query()->firstOrCreate(
                ['patient_id' => $related->id, 'related_patient_id' => $patient->id, 'type' => $type->inverse()->value],
                ['notes' => $notes, 'created_by' => $actor?->id],
            );

            return [$forward, $backward];
        });
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function related(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'related_patient_id');
    }

    public function auditPatientId(): ?int
    {
        return $this->patient_id;
    }

    public function auditLabel(): string
    {
        return "Relationship {$this->type->label()} between patients #{$this->patient_id} and #{$this->related_patient_id}";
    }
}

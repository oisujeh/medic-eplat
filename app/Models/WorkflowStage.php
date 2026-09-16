<?php

namespace App\Models;

use App\Enums\EncounterType;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One step of a pathway: a service point, the encounter it opens, the fee
 * it posts, and whether a visit may start there.
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $service_point_id
 * @property int $position
 * @property EncounterType|null $encounter_type
 * @property string|null $charge_code
 * @property bool $is_entry
 */
#[Fillable(['workflow_id', 'service_point_id', 'position', 'encounter_type', 'charge_code', 'is_entry'])]
class WorkflowStage extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encounter_type' => EncounterType::class,
            'is_entry' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * The steps that may follow this one, suggested first.
     *
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_stage_id')->orderByDesc('is_default')->orderBy('sort_order');
    }

    public function auditLabel(): string
    {
        return "Pathway step #{$this->id} (workflow #{$this->workflow_id}, service point #{$this->service_point_id})";
    }
}

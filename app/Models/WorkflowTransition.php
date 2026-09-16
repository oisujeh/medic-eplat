<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One allowed move between two steps of a pathway.
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $from_stage_id
 * @property int $to_stage_id
 * @property bool $is_default
 * @property int $sort_order
 */
#[Fillable(['workflow_id', 'from_stage_id', 'to_stage_id', 'is_default', 'sort_order'])]
class WorkflowTransition extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function auditLabel(): string
    {
        return "Pathway transition #{$this->from_stage_id} → #{$this->to_stage_id}";
    }
}

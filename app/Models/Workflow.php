<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A clinical pathway: the steps a visit moves through and the order they
 * may follow. Configuration, not code.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'description', 'is_default', 'is_active', 'sort_order'])]
class Workflow extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Workflow>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<WorkflowStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('position');
    }

    /**
     * @return HasMany<WorkflowTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    /**
     * The step at a service point, if the pathway has one there.
     */
    public function stageAt(ServicePoint|int $servicePoint): ?WorkflowStage
    {
        $id = $servicePoint instanceof ServicePoint ? $servicePoint->id : $servicePoint;

        return $this->stages->firstWhere('service_point_id', $id);
    }

    public function startsAt(ServicePoint|int $servicePoint): bool
    {
        return (bool) $this->stageAt($servicePoint)?->is_entry;
    }

    public function auditLabel(): string
    {
        return "Pathway {$this->name}";
    }
}

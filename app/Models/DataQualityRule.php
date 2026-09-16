<?php

namespace App\Models;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualitySeverity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCatalogueVersions;
use App\Models\Contracts\AuditableRecord;
use App\Support\DataQuality\DataQualityCheck;
use App\Support\DataQuality\DataQualityChecks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One data-quality check as this facility has configured it. The check itself
 * lives in code; this row holds what may be tuned — severity, the role that
 * owns the work, the thresholds the check reads, and whether it runs.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property DataQualityDimension $dimension
 * @property DataQualitySeverity $severity
 * @property string|null $description
 * @property string|null $owner_role
 * @property array<string, mixed> $parameters
 * @property int $sort_order
 * @property bool $is_active
 * @property-read int|null $open_issues_count
 */
#[Fillable([
    'key', 'name', 'dimension', 'severity', 'description', 'owner_role',
    'parameters', 'sort_order', 'is_active',
])]
class DataQualityRule extends Model implements AuditableRecord
{
    use Auditable, HasCatalogueVersions;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => DataQualityDimension::class,
            'severity' => DataQualitySeverity::class,
            'parameters' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<DataQualityRule>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<DataQualityIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class);
    }

    /**
     * The check this row configures, or null when the row names a check the
     * code no longer has (a rule removed in an upgrade).
     */
    public function check(): ?DataQualityCheck
    {
        return DataQualityChecks::find($this->key);
    }

    /**
     * The parameters the check will read: what is stored, with anything the
     * check has since added filled in from its defaults.
     *
     * @return array<string, mixed>
     */
    public function effectiveParameters(): array
    {
        $check = $this->check();

        return $check === null
            ? $this->parameters
            : [...$check::defaultParameters(), ...$this->parameters];
    }

    /**
     * The attributes whose change opens a new rule version. Activation and
     * ordering are housekeeping, not a change of meaning.
     *
     * @return list<string>
     */
    public function versionedAttributes(): array
    {
        return ['name', 'dimension', 'severity', 'owner_role', 'parameters'];
    }

    public function auditLabel(): string
    {
        return "Data quality rule: {$this->name}";
    }
}

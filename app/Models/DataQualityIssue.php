<?php

namespace App\Models;

use App\Enums\DataQualityDimension;
use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualitySeverity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One record failing one rule, carrying a snapshot of the rule as it stood
 * when the issue was raised.
 *
 * @property int $id
 * @property int $data_quality_rule_id
 * @property int|null $catalogue_version_id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $patient_id
 * @property DataQualityDimension $dimension
 * @property DataQualitySeverity $severity
 * @property string|null $owner_role
 * @property string $summary
 * @property array<string, mixed>|null $detail
 * @property DataQualityIssueStatus $status
 * @property Carbon $first_detected_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution
 * @property Carbon|null $created_at
 * @property-read DataQualityRule $rule
 * @property-read Model|null $subject
 * @property-read Patient|null $patient
 * @property-read User|null $resolvedBy
 */
#[Fillable([
    'data_quality_rule_id', 'catalogue_version_id', 'subject_type', 'subject_id', 'patient_id',
    'dimension', 'severity', 'owner_role', 'summary', 'detail',
    'status', 'first_detected_at', 'last_seen_at', 'resolved_at', 'resolved_by', 'resolution',
])]
class DataQualityIssue extends Model implements AuditableRecord
{
    use Auditable {
        auditExcludedAttributes as defaultAuditExcludedAttributes;
    }
    use HasPublicId;

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
            'status' => DataQualityIssueStatus::class,
            'detail' => 'array',
            'first_detected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DataQualityRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(DataQualityRule::class, 'data_quality_rule_id');
    }

    /**
     * @return BelongsTo<CatalogueVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(CatalogueVersion::class, 'catalogue_version_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<DataQualityIssue>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', DataQualityIssueStatus::Open);
    }

    /**
     * Issues a role owns: those routed to it, and those routed to nobody.
     *
     * @param  Builder<DataQualityIssue>  $query
     * @param  array<int, string>  $roleSlugs
     */
    #[Scope]
    protected function ownedByAny(Builder $query, array $roleSlugs): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('owner_role')->orWhereIn('owner_role', $roleSlugs));
    }

    /**
     * Re-confirming an open issue is not an audit event: the sweep touches
     * last_seen_at on every issue it finds still failing, and nothing about
     * the record has changed.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [...$this->defaultAuditExcludedAttributes(), 'last_seen_at'];
    }

    /**
     * How long the issue has been outstanding, in whole days.
     */
    public function ageInDays(): int
    {
        return (int) $this->first_detected_at->diffInDays(now());
    }

    public function auditLabel(): string
    {
        return "Data quality issue: {$this->summary}";
    }
}

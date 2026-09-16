<?php

namespace App\Models;

use App\Enums\LabDepartment;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCatalogueVersions;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\LabTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LabDepartment $department
 * @property string|null $specimen_type
 * @property string|null $unit
 * @property float|null $reference_low
 * @property float|null $reference_high
 * @property string|null $reference_text
 * @property float|null $price
 * @property int|null $turnaround_hours
 * @property bool $is_panel
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'concept_id', 'code', 'name', 'department', 'specimen_type', 'unit', 'reference_low',
    'reference_high', 'reference_text', 'price', 'turnaround_hours',
    'is_panel', 'is_active', 'sort_order',
])]
class LabTest extends Model implements AuditableRecord
{
    /** @use HasFactory<LabTestFactory> */
    use Auditable, HasCatalogueVersions, HasFactory;

    /**
     * Reference limits by age band and sex, most specific first.
     *
     * @return HasMany<LabReferenceRange, $this>
     */
    public function referenceRanges(): HasMany
    {
        return $this->hasMany(LabReferenceRange::class)
            ->orderByRaw('sex is null')
            ->orderByDesc('age_min_days');
    }

    /**
     * The limits that apply to a patient of this age (in days) and sex:
     * the most specific banded range, or the test's own single range.
     *
     * @return array{low: float|null, high: float|null, critical_low: float|null, critical_high: float|null, text: string|null}
     */
    public function rangeFor(?int $ageDays, ?string $sex): array
    {
        $band = $this->referenceRanges
            ->first(fn (LabReferenceRange $range) => $range->covers($ageDays, $sex));

        if ($band !== null) {
            return [
                'low' => $band->low,
                'high' => $band->high,
                'critical_low' => $band->critical_low,
                'critical_high' => $band->critical_high,
                'text' => $band->text ?? $this->reference_text,
            ];
        }

        return [
            'low' => $this->reference_low,
            'high' => $this->reference_high,
            'critical_low' => null,
            'critical_high' => null,
            'text' => $this->reference_text,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department' => LabDepartment::class,
            'reference_low' => 'float',
            'reference_high' => 'float',
            'price' => 'float',
            'turnaround_hours' => 'integer',
            'is_panel' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The component analytes that make up a panel.
     *
     * @return BelongsToMany<LabTest, $this>
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class, 'lab_panel_items', 'panel_id', 'test_id')
            ->withPivot('sort_order')
            ->orderBy('lab_panel_items.sort_order');
    }

    /**
     * Flatten this test to the analytes that should be resulted: a panel's
     * components, or the test itself when it stands alone.
     *
     * @return Collection<int, LabTest>
     */
    public function resultableTests(): Collection
    {
        if ($this->is_panel) {
            return $this->components()->where('is_active', true)->get();
        }

        /** @var Collection<int, LabTest> */
        return new Collection([$this]);
    }

    /**
     * A display string for the reference range, e.g. "13 – 17 g/dL" or "Negative".
     */
    public function referenceLabel(): ?string
    {
        if ($this->reference_low !== null || $this->reference_high !== null) {
            $range = trim(sprintf(
                '%s – %s',
                $this->reference_low !== null ? rtrim(rtrim((string) $this->reference_low, '0'), '.') : '',
                $this->reference_high !== null ? rtrim(rtrim((string) $this->reference_high, '0'), '.') : '',
            ), ' –');

            return trim($range.' '.($this->unit ?? ''));
        }

        return $this->reference_text;
    }

    /**
     * Scope to active tests, ordered for display.
     *
     * @param  Builder<LabTest>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The terminology concept this entry is coded to, when it is.
     *
     * @return BelongsTo<Concept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /**
     * The attributes whose change opens a new catalogue version.
     *
     * @return list<string>
     */
    public function versionedAttributes(): array
    {
        return ['name', 'department', 'specimen_type', 'unit', 'reference_low', 'reference_high', 'reference_text', 'price', 'turnaround_hours', 'is_panel'];
    }
}

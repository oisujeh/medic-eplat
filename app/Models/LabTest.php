<?php

namespace App\Models;

use App\Enums\LabDepartment;
use Database\Factories\LabTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    'code', 'name', 'department', 'specimen_type', 'unit', 'reference_low',
    'reference_high', 'reference_text', 'price', 'turnaround_hours',
    'is_panel', 'is_active', 'sort_order',
])]
class LabTest extends Model
{
    /** @use HasFactory<LabTestFactory> */
    use HasFactory;

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
}

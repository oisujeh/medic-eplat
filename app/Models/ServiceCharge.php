<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\ServiceChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ServiceCategory $category
 * @property string|null $unit
 * @property float $price
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'category', 'unit', 'price', 'is_active', 'sort_order'])]
class ServiceCharge extends Model implements AuditableRecord
{
    /** @use HasFactory<ServiceChargeFactory> */
    use Auditable, HasFactory;

    /** The well-known code whose price is auto-charged when a consultation completes. */
    public const CONSULTATION = 'CONSULTATION';

    /** The fee schedule code posted when a patient is placed in a bed. */
    public const ADMISSION = 'ADMISSION';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'price' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * A display label including the unit, e.g. "Bed — ICU (per day)".
     */
    public function label(): string
    {
        return $this->name.($this->unit ? " ({$this->unit})" : '');
    }

    /**
     * Scope to active services, ordered for display.
     *
     * @param  Builder<ServiceCharge>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

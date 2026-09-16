<?php

namespace App\Models;

use App\Enums\FundingSource;
use App\Enums\InventoryCategory;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property InventoryCategory $category
 * @property string|null $generic_name
 * @property string|null $form
 * @property string|null $strength
 * @property string|null $route
 * @property string $unit
 * @property int|null $pack_size
 * @property string|null $brand
 * @property string|null $nafdac_number
 * @property FundingSource|null $funding_source
 * @property float|null $cost_price
 * @property float $selling_price
 * @property int $reorder_level
 * @property int $quantity_on_hand
 * @property bool $is_active
 */
#[Fillable([
    'concept_id', 'code', 'name', 'generic_name', 'category', 'form', 'strength', 'route', 'unit',
    'pack_size', 'brand', 'nafdac_number', 'funding_source', 'cost_price',
    'selling_price', 'reorder_level', 'quantity_on_hand', 'is_active',
])]
class InventoryItem extends Model implements AuditableRecord
{
    /** @use HasFactory<InventoryItemFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'funding_source' => FundingSource::class,
            'pack_size' => 'integer',
            'cost_price' => 'float',
            'selling_price' => 'float',
            'reorder_level' => 'integer',
            'quantity_on_hand' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether stock has fallen to or below the reorder level.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }

    /**
     * A display label, e.g. "Amlodipine 5mg (Tablet)".
     */
    public function label(): string
    {
        return trim(collect([$this->name, $this->strength])->filter()->implode(' '))
            .($this->form ? " ({$this->form})" : '');
    }

    /**
     * Quantity available to dispense right now (excludes expired batches),
     * at one location or across all of them.
     */
    public function dispensableQuantity(?StockLocation $location = null): int
    {
        return (int) $this->dispensableBatches($location)->sum('quantity');
    }

    /**
     * Quantity held at a location, expired batches included.
     */
    public function quantityAt(StockLocation $location): int
    {
        return (int) $this->batches()->where('stock_location_id', $location->id)->sum('quantity');
    }

    /**
     * Dispensable batches, first-expiry-first-out (undated batches last),
     * at one location or across all of them.
     *
     * @return HasMany<StockBatch, $this>
     */
    public function dispensableBatches(?StockLocation $location = null): HasMany
    {
        $batches = $this->batches();

        if ($location !== null) {
            $batches->where('stock_location_id', $location->id);
        }

        return $batches
            ->where('quantity', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString()))
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    /**
     * Prescriptions written for this product.
     *
     * @return HasMany<Medication, $this>
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * Scope to active items, ordered for display.
     *
     * @param  Builder<InventoryItem>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('name');
    }

    /**
     * Scope to items at or below their reorder level.
     *
     * @param  Builder<InventoryItem>  $query
     */
    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    /**
     * Scope to the stocked products of a drug concept: what the pharmacy can
     * supply against a prescription for that drug.
     *
     * @param  Builder<InventoryItem>  $query
     */
    #[Scope]
    protected function productsOf(Builder $query, int $conceptId): void
    {
        $query->where('concept_id', $conceptId)->where('is_active', true)->orderBy('strength')->orderBy('form');
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
}

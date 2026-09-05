<?php

namespace App\Models;

use App\Enums\StockLocationKind;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\StockLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place stock is kept: the main store, the dispensary, a ward cupboard.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property StockLocationKind $kind
 * @property int|null $service_point_id
 * @property bool $receives_deliveries
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'kind', 'service_point_id', 'receives_deliveries', 'is_active', 'sort_order'])]
class StockLocation extends Model implements AuditableRecord
{
    /** @use HasFactory<StockLocationFactory> */
    use Auditable, HasFactory;

    public const MAIN_STORE = 'main-store';

    public const DISPENSARY = 'dispensary';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StockLocationKind::class,
            'receives_deliveries' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * Requisitions this location raised.
     *
     * @return HasMany<StockRequisition, $this>
     */
    public function requisitionsRaised(): HasMany
    {
        return $this->hasMany(StockRequisition::class, 'to_location_id');
    }

    /**
     * Requisitions this location is asked to supply.
     *
     * @return HasMany<StockRequisition, $this>
     */
    public function requisitionsToSupply(): HasMany
    {
        return $this->hasMany(StockRequisition::class, 'from_location_id');
    }

    /**
     * Scope to active locations, in display order.
     *
     * @param  Builder<StockLocation>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function auditLabel(): string
    {
        return "Stock location {$this->name}";
    }
}

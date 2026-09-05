<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\StockRequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A request for stock from one location to another.
 *
 * @property int $id
 * @property string $number
 * @property int $from_location_id
 * @property int $to_location_id
 * @property RequisitionStatus $status
 * @property string|null $note
 * @property int|null $requested_by
 * @property Carbon|null $requested_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 */
#[Fillable([
    'number', 'from_location_id', 'to_location_id', 'status', 'note',
    'requested_by', 'requested_at', 'approved_by', 'approved_at',
    'issued_by', 'issued_at', 'received_by', 'received_at',
    'cancelled_by', 'cancelled_at', 'cancel_reason',
])]
class StockRequisition extends Model implements AuditableRecord
{
    /** @use HasFactory<StockRequisitionFactory> */
    use Auditable, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockRequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockRequisitionLine::class);
    }

    /**
     * The location asked to supply the stock.
     *
     * @return BelongsTo<StockLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * The location that raised the request and will receive the stock.
     *
     * @return BelongsTo<StockLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Scope to requisitions still moving through the store.
     *
     * @param  Builder<StockRequisition>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereIn('status', [
            RequisitionStatus::Requested->value,
            RequisitionStatus::Approved->value,
            RequisitionStatus::Issued->value,
        ]);
    }

    public function auditLabel(): string
    {
        return "Requisition {$this->number}";
    }
}

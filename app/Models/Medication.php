<?php

namespace App\Models;

use App\Enums\MedicationFulfilment;
use App\Exceptions\PrescriptionOverfilledException;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $encounter_id
 * @property int|null $prescribed_by
 * @property int|null $concept_id
 * @property int|null $inventory_item_id
 * @property string $name
 * @property string|null $dose
 * @property string|null $frequency
 * @property string|null $route
 * @property int|null $quantity
 * @property int|null $duration_days
 * @property string $status
 * @property MedicationFulfilment $fulfilment
 * @property int $quantity_dispensed
 * @property Carbon|null $started_at
 * @property Carbon|null $stopped_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 */
#[Fillable([
    'concept_id', 'inventory_item_id', 'patient_id', 'visit_id', 'encounter_id', 'prescribed_by', 'name',
    'dose', 'frequency', 'route', 'quantity', 'duration_days', 'status', 'fulfilment', 'quantity_dispensed',
    'started_at', 'stopped_at', 'notes',
])]
class Medication extends Model implements AuditableRecord
{
    /** @use HasFactory<MedicationFactory> */
    use Auditable, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'duration_days' => 'integer',
            'quantity_dispensed' => 'integer',
            'fulfilment' => MedicationFulfilment::class,
            'started_at' => 'date',
            'stopped_at' => 'date',
        ];
    }

    /**
     * Limit the query to medications the patient is currently taking.
     *
     * @param  Builder<Medication>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Limit the query to prescriptions the pharmacy still has to supply.
     *
     * @param  Builder<Medication>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE)
            ->whereIn('fulfilment', [MedicationFulfilment::Pending->value, MedicationFulfilment::Partial->value]);
    }

    /**
     * How much of the prescribed quantity is still to be supplied; null when
     * no quantity was prescribed, so any supply completes the line.
     */
    public function quantityRemaining(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }

        return max(0, $this->quantity - $this->quantity_dispensed);
    }

    /**
     * Record a supply against the prescription and move its fulfilment on.
     * Supplying more than was prescribed is refused: the pharmacist can
     * still dispense the extra from stock without linking it to the line.
     */
    public function recordDispensed(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A dispensed quantity must be at least one.');
        }

        $remaining = $this->quantityRemaining();

        if ($remaining !== null && $quantity > $remaining) {
            throw new PrescriptionOverfilledException(
                "{$this->label()}: only {$remaining} of the prescribed {$this->quantity} remain to be dispensed."
            );
        }

        $this->quantity_dispensed += $quantity;
        $this->fulfilment = $this->quantity === null || $this->quantity_dispensed >= $this->quantity
            ? MedicationFulfilment::Dispensed
            : MedicationFulfilment::Partial;
        $this->save();
    }

    /**
     * A compact human-readable summary, e.g. "Amlodipine 5mg OD".
     */
    public function label(): string
    {
        return trim(collect([$this->name, $this->dose, $this->frequency])
            ->filter()
            ->implode(' '));
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
    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
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
     * The stocked product the prescriber chose, when the drug is stocked.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * What the pharmacy handed over against this prescription.
     *
     * @return HasMany<DispenseItem, $this>
     */
    public function dispenseItems(): HasMany
    {
        return $this->hasMany(DispenseItem::class);
    }
}

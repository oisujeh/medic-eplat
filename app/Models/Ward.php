<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Enums\WardType;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A ward: a named group of beds, optionally priced per day from the fee
 * schedule.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property WardType $type
 * @property int|null $bed_service_charge_id
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'code', 'type', 'bed_service_charge_id', 'description', 'is_active', 'sort_order'])]
class Ward extends Model implements AuditableRecord
{
    /** @use HasFactory<WardFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WardType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Bed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class)->orderBy('sort_order')->orderBy('label');
    }

    /**
     * @return HasMany<Admission, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * The fee schedule entry that prices a day in one of this ward's beds.
     *
     * @return BelongsTo<ServiceCharge, $this>
     */
    public function bedCharge(): BelongsTo
    {
        return $this->belongsTo(ServiceCharge::class, 'bed_service_charge_id');
    }

    /**
     * Patients currently admitted to this ward.
     *
     * @return HasMany<Admission, $this>
     */
    public function inpatients(): HasMany
    {
        return $this->hasMany(Admission::class)->where('status', AdmissionStatus::Admitted->value);
    }

    /**
     * Add beds to the ward, continuing its numbering and skipping any label
     * already in use.
     *
     * @return Collection<int, Bed>
     */
    public function addBeds(int $count, string $prefix = 'Bed'): Collection
    {
        $existing = $this->beds()->pluck('label')->flip();
        $next = $this->beds()->count() + 1;
        $created = collect();

        while ($created->count() < $count) {
            $label = "{$prefix} {$next}";

            if (! $existing->has($label)) {
                $created->push($this->beds()->create(['label' => $label, 'sort_order' => $next]));
            }

            $next++;
        }

        return $created;
    }

    /**
     * Bed counts by status, for occupancy displays.
     *
     * @return array{total: int, available: int, occupied: int, out_of_service: int}
     */
    public function occupancy(): array
    {
        $beds = $this->relationLoaded('beds') ? $this->beds : $this->beds()->get();

        return [
            'total' => $beds->count(),
            'available' => $beds->where('status', BedStatus::Available)->count(),
            'occupied' => $beds->where('status', BedStatus::Occupied)->count(),
            'out_of_service' => $beds->where('status', BedStatus::OutOfService)->count(),
        ];
    }

    /**
     * Scope to wards accepting admissions, in display order.
     *
     * @param  Builder<Ward>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

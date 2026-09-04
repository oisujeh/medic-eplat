<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\BedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single bed in a ward.
 *
 * @property int $id
 * @property int $ward_id
 * @property string $label
 * @property BedStatus $status
 * @property int $sort_order
 * @property string|null $notes
 */
#[Fillable(['ward_id', 'label', 'status', 'sort_order', 'notes'])]
class Bed extends Model implements AuditableRecord
{
    /** @use HasFactory<BedFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BedStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * The admission currently occupying the bed, if any.
     *
     * @return HasOne<Admission, $this>
     */
    public function currentAdmission(): HasOne
    {
        return $this->hasOne(Admission::class)->where('status', AdmissionStatus::Admitted->value);
    }

    /**
     * Whether a patient can be placed in this bed right now.
     */
    public function isAvailable(): bool
    {
        return $this->status === BedStatus::Available;
    }

    /**
     * Scope to beds that can take a patient.
     *
     * @param  Builder<Bed>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('status', BedStatus::Available->value);
    }
}

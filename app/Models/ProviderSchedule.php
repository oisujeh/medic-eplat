<?php

namespace App\Models;

use Database\Factories\ProviderScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provider_id
 * @property int|null $service_point_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 * @property int $slot_minutes
 * @property bool $is_active
 */
#[Fillable([
    'provider_id', 'service_point_id', 'weekday',
    'start_time', 'end_time', 'slot_minutes', 'is_active',
])]
class ProviderSchedule extends Model
{
    /** @use HasFactory<ProviderScheduleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'slot_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }
}

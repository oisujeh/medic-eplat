<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry in the domain event journal: the durable record of every event
 * the application has published, in the order it happened.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $patient_id
 * @property int|null $actor_id
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 */
class DomainEventRecord extends Model
{
    use HasPublicId;

    protected $table = 'domain_events';

    public $timestamps = false;

    /**
     * Microsecond precision keeps events in the order they were published
     * even within a single request.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The journal is append-only.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Domain event journal entries are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Domain event journal entries cannot be deleted.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

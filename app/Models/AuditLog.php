<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry in the append-only audit trail.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $label
 * @property int|null $patient_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route
 * @property Carbon $occurred_at
 * @property string|null $previous_hash
 * @property string $hash
 */
class AuditLog extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_VIEWED = 'viewed';

    public const ACTION_EXPORTED = 'exported';

    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_LOGIN_FAILED = 'login_failed';

    /**
     * Every action the trail records, in display order.
     *
     * @var list<string>
     */
    public const ACTIONS = [
        self::ACTION_VIEWED,
        self::ACTION_CREATED,
        self::ACTION_UPDATED,
        self::ACTION_DELETED,
        self::ACTION_EXPORTED,
        self::ACTION_LOGIN,
        self::ACTION_LOGOUT,
        self::ACTION_LOGIN_FAILED,
    ];

    public $timestamps = false;

    /**
     * Microsecond precision so the hashed timestamp survives a round trip
     * through the database unchanged.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The trail is append-only: the application refuses to rewrite history.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit log entries are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit log entries cannot be deleted.');
        });
    }

    /**
     * The staff member who performed the action, if the account still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The patient whose record the action touched.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The record acted on, when it still exists.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A readable name for the record type ("Lab Result", "Bill Charge").
     */
    public function typeLabel(): ?string
    {
        return $this->auditable_type ? static::labelForType($this->auditable_type) : null;
    }

    /**
     * Turn a model class name into a readable type label.
     */
    public static function labelForType(string $type): string
    {
        return str(class_basename($type))->headline()->toString();
    }
}

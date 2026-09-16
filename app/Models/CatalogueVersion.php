<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One version of a catalogue entry: what it said and when that held.
 * Once written, only its closing date changes.
 *
 * @property int $id
 * @property string $versionable_type
 * @property int $versionable_id
 * @property int $version
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property array<string, mixed> $snapshot
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class CatalogueVersion extends Model implements AuditableRecord
{
    use Auditable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CatalogueVersion $version): void {
            if (array_diff(array_keys($version->getDirty()), ['effective_to']) !== []) {
                throw new LogicException('A catalogue version is immutable once written; only its closing date may change.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Catalogue versions cannot be deleted; records refer to them.');
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the version was in force on a date.
     */
    public function inForceOn(Carbon $date): bool
    {
        return $this->effective_from->lte($date) && ($this->effective_to === null || $this->effective_to->gte($date));
    }

    public function auditLabel(): string
    {
        return class_basename($this->versionable_type)." #{$this->versionable_id} v{$this->version}";
    }
}

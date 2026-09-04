<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Database\Factories\GlobalPropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A facility-wide configuration value, keyed by a dotted name such as
 * `facility.name`.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value', 'description'])]
class GlobalProperty extends Model implements AuditableRecord
{
    /** @use HasFactory<GlobalPropertyFactory> */
    use Auditable, HasFactory;

    /**
     * Read a property value, or the default when it has never been set.
     */
    public static function valueOf(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Write a property value, creating the property on first use. The
     * description is only written when given, so a later plain write keeps it.
     */
    public static function put(string $key, ?string $value, ?string $description = null): static
    {
        $attributes = ['value' => $value];

        if ($description !== null) {
            $attributes['description'] = $description;
        }

        return static::query()->updateOrCreate(['key' => $key], $attributes);
    }
}

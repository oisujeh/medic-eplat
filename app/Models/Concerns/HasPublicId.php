<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a record a ULID alongside its database key: a stable identifier that
 * can be printed on documents and used by the API and exports without
 * revealing how many rows the table holds.
 *
 * @property string $ulid
 */
trait HasPublicId
{
    /**
     * A fresh instance carries its ULID from construction, so the column is
     * set even where model events are off (seeders run without them).
     */
    public function initializeHasPublicId(): void
    {
        if (! array_key_exists('ulid', $this->attributes)) {
            $this->attributes['ulid'] = (string) Str::ulid();
        }
    }

    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', (string) Str::ulid());
            }
        });
    }
}

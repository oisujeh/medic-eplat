<?php

namespace App\Models;

use Database\Factories\IcdCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ICD-10 code from the facility's diagnosis catalogue.
 *
 * @property int $id
 * @property string $code
 * @property string $description
 * @property string|null $chapter
 * @property bool $is_active
 */
#[Fillable(['code', 'description', 'chapter', 'is_active'])]
class IcdCode extends Model
{
    /** @use HasFactory<IcdCodeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Problem, $this>
     */
    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }

    /**
     * The three-character category the code belongs to, e.g. "A01" for
     * "A01.0". Morbidity groupings are defined on categories.
     */
    public function category(): string
    {
        return static::categoryOf($this->code);
    }

    /**
     * Normalise a typed code: upper-case, trimmed, dot-separated subcategory.
     */
    public static function normalise(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = str_replace(' ', '', $code);

        if (strlen($code) > 3 && $code[3] !== '.') {
            $code = substr($code, 0, 3).'.'.substr($code, 3);
        }

        return $code;
    }

    /**
     * The three-character category of any ICD-10 code.
     */
    public static function categoryOf(string $code): string
    {
        return strtoupper(substr(trim($code), 0, 3));
    }

    /**
     * Find a catalogue entry by its code, however it was typed.
     */
    public static function findByCode(?string $code): ?static
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return static::query()->where('code', static::normalise($code))->first();
    }

    /**
     * Scope to codes offered to clinicians.
     *
     * @param  Builder<IcdCode>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

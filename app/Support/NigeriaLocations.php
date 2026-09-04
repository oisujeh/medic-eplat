<?php

namespace App\Support;

use Illuminate\Support\Collection;

class NigeriaLocations
{
    /**
     * Cached decoded dataset (state => list of LGAs).
     *
     * @var array<string, array<int, string>>|null
     */
    protected static ?array $data = null;

    /**
     * Path to the canonical states/LGA dataset that the frontend also imports.
     */
    public static function path(): string
    {
        return resource_path('js/data/state_lga.json');
    }

    /**
     * Get the full state => LGAs map.
     *
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        if (static::$data !== null) {
            return static::$data;
        }

        /** @var array<int, array{state: string, lgas: array<int, string>}> $entries */
        $entries = json_decode((string) file_get_contents(static::path()), true) ?: [];

        return static::$data = collect($entries)
            ->mapWithKeys(fn (array $entry) => [$entry['state'] => $entry['lgas']])
            ->all();
    }

    /**
     * The list of valid state names.
     *
     * @return array<int, string>
     */
    public static function states(): array
    {
        return array_keys(static::all());
    }

    /**
     * The LGAs belonging to a given state.
     *
     * @return Collection<int, string>
     */
    public static function lgasFor(?string $state): Collection
    {
        return collect(static::all()[$state] ?? []);
    }

    /**
     * Determine whether the given state exists.
     */
    public static function isValidState(?string $state): bool
    {
        return $state !== null && array_key_exists($state, static::all());
    }

    /**
     * Determine whether the LGA belongs to the given state.
     */
    public static function isValidLga(?string $state, ?string $lga): bool
    {
        return $lga !== null && static::lgasFor($state)->contains($lga);
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\CatalogueVersion;
use App\Services\AuditTrail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Keeps a catalogue entry's history. The entry's live row is always its
 * current meaning; every change to a versioned attribute closes the version
 * in force and opens the next, so records can point at the version they
 * were made under and read the same however the entry changes later.
 *
 * The model names the attributes that carry meaning in versionedAttributes();
 * housekeeping such as sort order or activation is not a new version.
 */
trait HasCatalogueVersions
{
    /**
     * The date the next version takes effect, when a change is being made
     * effective on a date other than today.
     */
    protected ?CarbonInterface $versionEffectiveFrom = null;

    /**
     * The attributes whose change means the entry now says something else.
     *
     * @return list<string>
     */
    abstract public function versionedAttributes(): array;

    public static function bootHasCatalogueVersions(): void
    {
        static::saved(function (self $model): void {
            $current = $model->openVersion();

            if ($current === null) {
                $model->writeVersion(1, Carbon::today());

                return;
            }

            $changed = array_intersect(array_keys($model->getChanges()), $model->versionedAttributes());

            if ($changed !== []) {
                $model->openNextVersion($current);
            }
        });
    }

    /**
     * @return MorphMany<CatalogueVersion, $this>
     */
    public function versions(): MorphMany
    {
        return $this->morphMany(CatalogueVersion::class, 'versionable')->orderByDesc('version');
    }

    /**
     * The version in force now. An entry loaded without one (seeded before
     * versioning, or created with events off) gets its first version here.
     */
    public function currentVersion(): CatalogueVersion
    {
        return $this->openVersion() ?? $this->writeVersion(1, Carbon::today());
    }

    /**
     * The version not yet closed, if the entry has one.
     */
    private function openVersion(): ?CatalogueVersion
    {
        return $this->versions()->whereNull('effective_to')->orderByDesc('version')->first();
    }

    /**
     * The version in force on a date, if the entry existed then.
     */
    public function versionAt(CarbonInterface $at): ?CatalogueVersion
    {
        $date = Carbon::instance($at)->startOfDay();

        return $this->versions()
            ->get()
            ->first(fn (CatalogueVersion $v) => $v->inForceOn($date));
    }

    /**
     * Make the next change take effect on a given date rather than today.
     */
    public function effectiveFrom(CarbonInterface $date): static
    {
        $this->versionEffectiveFrom = $date;

        return $this;
    }

    /**
     * The versioned attributes as they stand, JSON-safe.
     *
     * @return array<string, mixed>
     */
    public function versionSnapshot(): array
    {
        return Arr::only($this->attributesToArray(), $this->versionedAttributes());
    }

    private function openNextVersion(CatalogueVersion $current): void
    {
        $from = Carbon::instance($this->versionEffectiveFrom ?? Carbon::today())->startOfDay();
        $this->versionEffectiveFrom = null;

        $current->update(['effective_to' => $from->copy()->subDay()->max($current->effective_from)]);

        $this->writeVersion($current->version + 1, $from);
    }

    private function writeVersion(int $number, Carbon $from): CatalogueVersion
    {
        return $this->versions()->create([
            'version' => $number,
            'effective_from' => $from,
            'snapshot' => $this->versionSnapshot(),
            'reason' => app(AuditTrail::class)->currentReason(),
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}

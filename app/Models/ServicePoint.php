<?php

namespace App\Models;

use Database\Factories\ServicePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $module_slug
 * @property bool $captures_vitals
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'description', 'icon', 'module_slug', 'captures_vitals', 'sort_order', 'is_active'])]
class ServicePoint extends Model
{
    /** @use HasFactory<ServicePointFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captures_vitals' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * All queue entries ever routed to this service point.
     *
     * @return HasMany<QueueEntry, $this>
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * The staff who may be assigned to work this service point — users whose
     * roles are mapped to its governing module.
     *
     * @return Collection<int, User>
     */
    public function eligiblePersonnel(): Collection
    {
        if (! $this->module_slug) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        return User::query()
            ->active()
            ->whereHas('roles.modules', fn (Builder $q) => $q->where('modules.slug', $this->module_slug))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Scope a query to only active service points, ordered for display.
     *
     * @param  Builder<ServicePoint>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

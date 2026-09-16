<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $grants_all_modules
 */
#[Fillable(['name', 'slug', 'description', 'grants_all_modules'])]
class Role extends Model implements AuditableRecord
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grants_all_modules' => 'boolean',
        ];
    }

    /**
     * The users that belong to the role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The modules that this role can access.
     *
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }

    /**
     * The permissions granted to the role.
     *
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Replace the role's permissions with the given set, auditing each grant
     * and revocation.
     *
     * @param  list<Permission>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $wanted = array_unique(array_map(fn (Permission $p) => $p->value, $permissions));
        $current = $this->permissions()->get();

        $current
            ->reject(fn (RolePermission $grant) => in_array($grant->permission->value, $wanted, true))
            ->each(fn (RolePermission $grant) => $grant->delete());

        $held = $current->map(fn (RolePermission $grant) => $grant->permission->value)->all();

        foreach ($wanted as $value) {
            if (! in_array($value, $held, true)) {
                $this->permissions()->create(['permission' => $value]);
            }
        }
    }
}

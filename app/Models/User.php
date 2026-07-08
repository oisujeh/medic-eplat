<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $username
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'username', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The roles assigned to the user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Determine whether the user has the given role (by slug).
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * Determine whether the user has any of the given roles (by slug).
     *
     * @param  array<int, string>  $slugs
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    /**
     * Determine whether any of the user's roles grants access to every module.
     */
    public function hasFullModuleAccess(): bool
    {
        return $this->roles->contains('grants_all_modules', true);
    }

    /**
     * Get the active modules the user is allowed to access, ordered for display.
     *
     * @return Collection<int, Module>
     */
    public function accessibleModules(): Collection
    {
        if ($this->hasFullModuleAccess()) {
            return Module::active()->get();
        }

        return Module::active()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles->pluck('id')))
            ->get();
    }

    /**
     * Determine whether the user can access the module with the given slug.
     */
    public function canAccessModule(string $slug): bool
    {
        if ($this->hasFullModuleAccess()) {
            return Module::active()->where('slug', $slug)->exists();
        }

        return Module::active()
            ->where('slug', $slug)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles->pluck('id')))
            ->exists();
    }
}

<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\StaffCadre;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use App\Services\FacilitySettings;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property StaffCadre|null $cadre
 * @property string|null $registration_number
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deactivated_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'username', 'password', 'cadre', 'registration_number', 'phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements AuditableRecord, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The permission slugs the user's roles grant, resolved once per instance.
     *
     * @var list<string>|null
     */
    private ?array $grantedPermissions = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'cadre' => StaffCadre::class,
        ];
    }

    /**
     * Limit the query to accounts that have not been deactivated.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Limit the query to staff who may be named as the provider on an
     * encounter: active accounts of a clinical cadre.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function providers(Builder $query): void
    {
        $query->whereNull('deactivated_at')
            ->whereIn('cadre', array_map(
                fn (StaffCadre $c) => $c->value,
                array_filter(StaffCadre::cases(), fn (StaffCadre $c) => $c->isClinical()),
            ));
    }

    /**
     * Whether this member of staff may be recorded as an encounter's provider.
     */
    public function isProvider(): bool
    {
        return $this->cadre?->isClinical() ?? false;
    }

    /**
     * The service points this member of staff is posted to.
     *
     * @return BelongsToMany<ServicePoint, $this>
     */
    public function servicePoints(): BelongsToMany
    {
        return $this->belongsToMany(ServicePoint::class);
    }

    /**
     * Determine whether the account has been deactivated.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
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
     * Appointments where this user is the provider, soonest first.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'provider_id')->orderBy('scheduled_start');
    }

    /**
     * The user's weekly availability templates.
     *
     * @return HasMany<ProviderSchedule, $this>
     */
    public function providerSchedules(): HasMany
    {
        return $this->hasMany(ProviderSchedule::class, 'provider_id');
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
        $modules = $this->hasFullModuleAccess()
            ? Module::active()->get()
            : Module::active()
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles->pluck('id')))
                ->get();

        return $modules->reject(fn (Module $module) => $this->moduleSwitchedOff($module->slug))->values();
    }

    /**
     * Whether a facility-level switch has taken the module out of use for
     * everyone, whatever their role. Billing off hides billing and claims
     * (PRD BIL-01).
     */
    private function moduleSwitchedOff(string $slug): bool
    {
        return in_array($slug, Module::BILLING_MODULES, true)
            && ! app(FacilitySettings::class)->billingEnabled();
    }

    /**
     * Whether any of the user's roles grants the permission. A role that
     * grants all modules holds every permission.
     */
    public function hasPermission(Permission $permission): bool
    {
        if ($this->hasFullModuleAccess()) {
            return true;
        }

        $this->grantedPermissions ??= array_values(array_unique(
            RolePermission::query()
                ->whereIn('role_id', $this->roles->pluck('id'))
                ->pluck('permission')
                ->map(fn (Permission $p) => $p->value)
                ->all(),
        ));

        return in_array($permission->value, $this->grantedPermissions, true);
    }

    /**
     * The permissions the user holds, for the client to show or hide actions.
     *
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        return array_values(array_filter(
            array_map(fn (Permission $p) => $p->value, Permission::cases()),
            fn (string $slug) => $this->hasPermission(Permission::from($slug)),
        ));
    }

    /**
     * Determine whether the user can access the module with the given slug.
     */
    public function canAccessModule(string $slug): bool
    {
        if ($this->moduleSwitchedOff($slug)) {
            return false;
        }

        if ($this->hasFullModuleAccess()) {
            return Module::active()->where('slug', $slug)->exists();
        }

        return Module::active()
            ->where('slug', $slug)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles->pluck('id')))
            ->exists();
    }

    /**
     * How the account appears in the audit trail.
     */
    public function auditLabel(): string
    {
        return "Staff account {$this->name}";
    }
}

<?php

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\LabOrder;
use App\Models\NotifiableDisease;
use App\Models\Patient;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\NotifiableDiseasesSeeder;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function permissionUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('the seeder grants each role its default permissions', function () {
    expect(permissionUser(['laboratory-staff'])->hasPermission(Permission::VerifyLabResults))->toBeTrue()
        ->and(permissionUser(['physician'])->hasPermission(Permission::VerifyLabResults))->toBeFalse()
        ->and(permissionUser(['physician'])->hasPermission(Permission::Discharge))->toBeTrue()
        ->and(permissionUser(['nurse'])->hasPermission(Permission::Discharge))->toBeFalse()
        ->and(permissionUser(['cashier'])->hasPermission(Permission::RecordPayments))->toBeTrue()
        ->and(permissionUser(['records-officer'])->hasPermission(Permission::RecordPayments))->toBeFalse();
});

test('a role that grants all modules holds every permission', function () {
    $admin = permissionUser(['super-administrator']);

    foreach (Permission::cases() as $permission) {
        expect($admin->hasPermission($permission))->toBeTrue();
    }

    expect(RolePermission::where('role_id', Role::where('slug', 'super-administrator')->value('id'))->count())->toBe(0);
});

test('permissions are gate abilities', function () {
    expect(permissionUser(['cashier'])->can(Permission::ManageClaims->value))->toBeTrue()
        ->and(permissionUser(['physician'])->can(Permission::ManageClaims->value))->toBeFalse();
});

test('the route middleware refuses staff without the permission even inside their module', function () {
    // A physician can open the laboratory module but may not release results.
    $order = LabOrder::factory()->for(Patient::factory())->create();

    actingAs(permissionUser(['physician']))
        ->post(route('laboratory.verify', $order))
        ->assertForbidden();

    $this->seed(NotifiableDiseasesSeeder::class);

    actingAs(permissionUser(['physician']))
        ->patch(route('surveillance.diseases.update', NotifiableDisease::query()->firstOrFail()), ['is_active' => false])
        ->assertForbidden();
});

test('granting a permission to a role takes effect and is audited', function () {
    $role = Role::where('slug', 'physician')->firstOrFail();
    actingAs(permissionUser(['super-administrator']));

    $role->syncPermissions([...$role->permissions->map(fn (RolePermission $g) => $g->permission)->all(), Permission::VerifyLabResults]);

    expect(permissionUser(['physician'])->hasPermission(Permission::VerifyLabResults))->toBeTrue()
        ->and(AuditLog::query()
            ->where('auditable_type', RolePermission::class)
            ->where('action', AuditLog::ACTION_CREATED)
            ->where('new_values->permission', Permission::VerifyLabResults->value)
            ->exists())->toBeTrue();

    $role->syncPermissions([]);

    expect(permissionUser(['physician'])->hasPermission(Permission::Discharge))->toBeFalse()
        ->and(AuditLog::query()->where('auditable_type', RolePermission::class)->where('action', AuditLog::ACTION_DELETED)->count())
        ->toBeGreaterThan(0);
});

test('the client is told which permissions the user holds', function () {
    actingAs(permissionUser(['cashier']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains(Permission::RecordPayments->value)
                && ! collect($permissions)->contains(Permission::Discharge->value)));
});

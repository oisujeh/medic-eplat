<?php

use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * Create a user assigned the given role slugs.
 *
 * @param  array<int, string>  $roleSlugs
 */
function userWithRoles(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('seeder creates the nine roles and the module set', function () {
    expect(Role::count())->toBe(9);
    expect(Role::where('slug', 'super-administrator')->value('grants_all_modules'))->toBeTrue();
    expect(Module::whereIn('slug', ['dashboard', 'laboratory', 'pharmacy', 'billing'])->count())->toBe(4);
});

test('a user can hold multiple roles', function () {
    $user = userWithRoles(['physician', 'cashier']);

    expect($user->roles)->toHaveCount(2);
    expect($user->hasRole('physician'))->toBeTrue();
    expect($user->hasAnyRole(['nurse', 'cashier']))->toBeTrue();
    expect($user->hasAnyRole(['nurse', 'pharmacy-staff']))->toBeFalse();
});

test('a role only exposes its mapped modules', function () {
    $user = userWithRoles(['laboratory-staff']);

    $slugs = $user->accessibleModules()->pluck('slug')->all();

    expect($slugs)->toContain('dashboard', 'laboratory');
    expect($slugs)->not->toContain('pharmacy', 'billing', 'administration');
});

test('multiple roles union their modules', function () {
    $user = userWithRoles(['laboratory-staff', 'cashier']);

    $slugs = $user->accessibleModules()->pluck('slug')->all();

    expect($slugs)->toContain('dashboard', 'laboratory', 'billing');
    expect($slugs)->not->toContain('pharmacy');
});

test('super administrator can access every active module', function () {
    $user = userWithRoles(['super-administrator']);

    $accessible = $user->accessibleModules()->pluck('slug')->sort()->values();
    $allActive = Module::active()->pluck('slug')->sort()->values();

    expect($accessible->all())->toBe($allActive->all());
    expect($user->canAccessModule('administration'))->toBeTrue();
});

test('inactive modules are never accessible', function () {
    $user = userWithRoles(['super-administrator']);

    Module::where('slug', 'laboratory')->update(['is_active' => false]);
    $user->refresh();

    expect($user->accessibleModules()->pluck('slug'))->not->toContain('laboratory');
    expect($user->canAccessModule('laboratory'))->toBeFalse();
});

test('a user without any role sees no modules', function () {
    $user = User::factory()->create();

    expect($user->accessibleModules())->toHaveCount(0);
    expect($user->canAccessModule('dashboard'))->toBeFalse();
});

test('users can open a module they have access to', function () {
    $user = userWithRoles(['laboratory-staff']);

    $this->actingAs($user)
        ->get(route('modules.show', 'laboratory'))
        ->assertOk();
});

test('users are forbidden from modules they cannot access', function () {
    $user = userWithRoles(['laboratory-staff']);

    $this->actingAs($user)
        ->get(route('modules.show', 'pharmacy'))
        ->assertForbidden();
});

test('the module middleware blocks unauthorized access', function () {
    $user = userWithRoles(['cashier']);

    config()->set('app.debug', false);

    Route::middleware(['web', 'auth', 'module:laboratory'])
        ->get('/__test-lab', fn () => 'ok');

    $this->actingAs($user)->get('/__test-lab')->assertForbidden();

    $lab = userWithRoles(['laboratory-staff']);
    $this->actingAs($lab)->get('/__test-lab')->assertOk();
});

test('accessible modules are shared with the frontend', function () {
    $user = userWithRoles(['cashier']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.roles', ['cashier'])
            ->where('auth.modules', fn ($modules) => collect($modules)->pluck('slug')->contains('billing')
                && ! collect($modules)->pluck('slug')->contains('laboratory')
            )
        );
});

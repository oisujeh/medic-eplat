<?php

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * Create a user assigned the given role slugs.
 *
 * @param  array<int, string>  $roleSlugs
 */
function staffWithRoles(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Resolve a role id by slug.
 */
function roleId(string $slug): int
{
    return Role::where('slug', $slug)->value('id');
}

test('an administrator sees the staff account register', function () {
    $admin = staffWithRoles(['super-administrator']);
    staffWithRoles(['physician']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Users')
            ->has('users.data', 2)
            ->has('roles', 9)
        );
});

test('the register paginates at fifteen accounts a page', function () {
    $admin = staffWithRoles(['super-administrator']);
    User::factory()->count(20)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 15)
            ->where('users.total', 21)
            ->where('users.current_page', 1)
        );

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 6)
            ->where('users.current_page', 2)
        );
});

test('pagination keeps active staff ahead of the deactivated archive', function () {
    $admin = staffWithRoles(['super-administrator']);

    // Enough deactivated accounts to fill a page on their own, named to sort
    // first alphabetically so only the ordering can put them last.
    User::factory()->count(15)->create(['name' => 'AAA Departed', 'deactivated_at' => now()]);
    User::factory()->count(3)->create(['name' => 'ZZZ Working']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->take(4)->every(fn ($u) => $u['is_active'] === true))
        );
});

test('deletability is still resolved correctly on a later page', function () {
    $admin = staffWithRoles(['super-administrator']);
    User::factory()->count(15)->create(['name' => 'AAA Filler']);

    $referenced = User::factory()->create(['name' => 'ZZZ Referenced']);
    Patient::factory()->create(['registered_by' => $referenced->id]);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)
                ->firstWhere('id', $referenced->id)['can_be_deleted'] === false
            )
        );
});

test('an administrator can create a user and assign roles', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Amaka Obi',
            'username' => 'a.obi',
            'email' => 'a.obi@facility.test',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'roles' => [roleId('physician'), roleId('nurse')],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $created = User::where('email', 'a.obi@facility.test')->firstOrFail();

    expect($created->name)->toBe('Amaka Obi');
    expect($created->username)->toBe('a.obi');
    expect(Hash::check('Str0ng-Passw0rd!', $created->password))->toBeTrue();
    expect($created->roles->pluck('slug')->sort()->values()->all())->toBe(['nurse', 'physician']);
});

test('a created account can sign in and reach its modules immediately', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Lab Tech',
        'username' => 'lab.tech',
        'email' => 'lab.tech@facility.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
        'roles' => [roleId('laboratory-staff')],
    ]);

    $created = User::where('email', 'lab.tech@facility.test')->firstOrFail();

    // Verified on creation, otherwise the `verified` middleware locks them out.
    expect($created->email_verified_at)->not->toBeNull();

    $this->actingAs($created)->get(route('laboratory.index'))->assertOk();
    $this->actingAs($created)->get(route('admin.users.index'))->assertForbidden();
});

test('an administrator can change a user\'s roles', function () {
    $admin = staffWithRoles(['super-administrator']);
    $staff = staffWithRoles(['physician']);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $staff), [
            'name' => $staff->name,
            'username' => $staff->username,
            'email' => $staff->email,
            'roles' => [roleId('cashier')],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->roles->pluck('slug')->all())->toBe(['cashier']);
});

test('non-administrators cannot reach user management', function (string $slug) {
    $user = staffWithRoles([$slug]);

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();

    $this->actingAs($user)->post(route('admin.users.store'), [
        'name' => 'Sneaky',
        'username' => 'sneaky',
        'email' => 'sneaky@facility.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
        'roles' => [roleId('super-administrator')],
    ])->assertForbidden();

    expect(User::where('email', 'sneaky@facility.test')->exists())->toBeFalse();
})->with(['chief-medical-director', 'physician', 'nurse', 'records-officer', 'cashier']);

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('a user must be created with at least one role', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'No Role',
            'username' => 'no.role',
            'email' => 'no.role@facility.test',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'roles' => [],
        ])
        ->assertSessionHasErrors('roles');

    expect(User::where('email', 'no.role@facility.test')->exists())->toBeFalse();
});

test('duplicate emails and usernames are rejected', function () {
    $admin = staffWithRoles(['super-administrator']);
    $existing = staffWithRoles(['physician']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Clash',
            'username' => $existing->username,
            'email' => $existing->email,
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'roles' => [roleId('nurse')],
        ])
        ->assertSessionHasErrors(['email', 'username']);
});

test('an unknown role id is rejected', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Ghost Role',
            'username' => 'ghost.role',
            'email' => 'ghost.role@facility.test',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'roles' => [9999],
        ])
        ->assertSessionHasErrors('roles.0');
});

test('an administrator cannot remove their own super administrator role', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'roles' => [roleId('physician')],
        ])
        ->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole('super-administrator'))->toBeTrue();
});

test('an administrator can still edit their own details while keeping the role', function () {
    $admin = staffWithRoles(['super-administrator']);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'name' => 'Renamed Admin',
            'username' => $admin->username,
            'email' => $admin->email,
            'roles' => [roleId('super-administrator'), roleId('physician')],
        ])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->name)->toBe('Renamed Admin');
    expect($admin->fresh()->roles->pluck('slug')->sort()->values()->all())
        ->toBe(['physician', 'super-administrator']);
});

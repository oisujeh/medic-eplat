<?php

use App\Models\Patient;
use App\Models\ProviderSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * Create a user assigned the given role slugs.
 *
 * @param  array<int, string>  $roleSlugs
 */
function accountWithRoles(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function administrator(): User
{
    return accountWithRoles(['super-administrator']);
}

// ---------------------------------------------------------------- deactivate

test('an administrator can deactivate a member of staff', function () {
    $admin = administrator();
    $staff = accountWithRoles(['physician']);

    $this->actingAs($admin)
        ->post(route('admin.users.deactivate', $staff))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->isDeactivated())->toBeTrue();
});

test('a deactivated member of staff keeps their roles and records', function () {
    $admin = administrator();
    $staff = accountWithRoles(['records-officer']);
    Patient::factory()->create(['registered_by' => $staff->id]);

    $this->actingAs($admin)->post(route('admin.users.deactivate', $staff));

    expect($staff->fresh()->roles)->toHaveCount(1);
    expect(Patient::where('registered_by', $staff->id)->exists())->toBeTrue();
});

test('a deactivated member of staff cannot sign in', function () {
    $staff = accountWithRoles(['physician']);

    // Control: these exact credentials work while the account is active, so the
    // refusal below is the deactivation and not a broken login.
    $this->post(route('login.store'), ['login' => $staff->email, 'password' => 'password']);
    $this->assertAuthenticated();
    $this->post(route('logout'));

    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->post(route('login.store'), ['login' => $staff->email, 'password' => 'password'])
        ->assertSessionHasErrors();

    $this->assertGuest();
});

test('deactivating ends a session the member of staff already holds', function () {
    $staff = accountWithRoles(['physician']);

    // They are signed in and working when the administrator deactivates them.
    $this->actingAs($staff)->get(route('dashboard'))->assertOk();

    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('an administrator can reactivate a member of staff', function () {
    $admin = administrator();
    $staff = accountWithRoles(['physician']);
    $staff->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($admin)
        ->post(route('admin.users.reactivate', $staff))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->isDeactivated())->toBeFalse();
});

test('an administrator cannot deactivate themselves', function () {
    $admin = administrator();

    $this->actingAs($admin)
        ->post(route('admin.users.deactivate', $admin))
        ->assertSessionHasErrors('delete');

    expect($admin->fresh()->isDeactivated())->toBeFalse();
});

test('deactivated staff drop out of provider and assignee pickers', function () {
    $active = accountWithRoles(['physician']);
    $leaving = accountWithRoles(['physician']);
    $leaving->forceFill(['deactivated_at' => now()])->save();

    $providers = User::query()
        ->active()
        ->whereHas('roles.modules', fn ($m) => $m->where('modules.slug', 'clinical'))
        ->pluck('id');

    expect($providers)->toContain($active->id);
    expect($providers)->not->toContain($leaving->id);
});

// -------------------------------------------------------------------- delete

test('an administrator can delete an account created in error', function () {
    $admin = administrator();
    $mistake = accountWithRoles(['nurse']);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $mistake))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(User::find($mistake->id))->toBeNull();
    expect(DB::table('role_user')->where('user_id', $mistake->id)->exists())->toBeFalse();
});

test('an account referenced by clinical records cannot be deleted', function () {
    $admin = administrator();
    $clinician = accountWithRoles(['physician']);
    $patient = Patient::factory()->create(['registered_by' => $clinician->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $clinician))
        ->assertSessionHasErrors('delete');

    // The account survives, and so does the attribution on the record.
    expect(User::find($clinician->id))->not->toBeNull();
    expect($patient->fresh()->registered_by)->toBe($clinician->id);
});

test('deleting a provider never cascades away their schedules', function () {
    $admin = administrator();
    $provider = accountWithRoles(['physician']);
    ProviderSchedule::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $provider))
        ->assertSessionHasErrors('delete');

    expect(ProviderSchedule::where('provider_id', $provider->id)->exists())->toBeTrue();
});

test('an administrator cannot delete themselves', function () {
    $admin = administrator();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('delete');

    expect(User::find($admin->id))->not->toBeNull();
});

test('the refusal names the records standing in the way', function () {
    $admin = administrator();
    $clinician = accountWithRoles(['physician']);
    Patient::factory()->count(2)->create(['registered_by' => $clinician->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $clinician))
        ->assertSessionHasErrors('delete');

    $message = session('errors')->first('delete');

    expect($message)->toContain('2 patients');
    expect($message)->toContain('Deactivate it instead');
});

// ------------------------------------------------------------- authorization

test('non-administrators cannot deactivate or delete accounts', function (string $slug) {
    $actor = accountWithRoles([$slug]);
    $target = accountWithRoles(['nurse']);

    $this->actingAs($actor)->post(route('admin.users.deactivate', $target))->assertForbidden();
    $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();

    expect($target->fresh()->isDeactivated())->toBeFalse();
    expect(User::find($target->id))->not->toBeNull();
})->with(['chief-medical-director', 'physician', 'nurse', 'cashier']);

// --------------------------------------------------------------- the listing

test('the register costs the same number of queries however many staff there are', function () {
    $admin = administrator();
    accountWithRoles(['physician']);

    // Warm the connection's schema introspection so its one-off cost is not
    // mistaken for per-row work.
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $baseline = count(DB::getQueryLog());

    User::factory()->count(15)->create();

    DB::flushQueryLog();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $withMoreStaff = count(DB::getQueryLog());

    DB::disableQueryLog();

    // Deletability is resolved in one pass, not per row.
    expect($withMoreStaff)->toBe($baseline);
});

test('the register reports activity and deletability for each account', function () {
    $admin = administrator();
    $clean = accountWithRoles(['nurse']);
    $referenced = accountWithRoles(['physician']);
    Patient::factory()->create(['registered_by' => $referenced->id]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $clean->id)['can_be_deleted'] === true
                && collect($users)->firstWhere('id', $referenced->id)['can_be_deleted'] === false
                && collect($users)->firstWhere('id', $referenced->id)['is_active'] === true
            )
        );
});

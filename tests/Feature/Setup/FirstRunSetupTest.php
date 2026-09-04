<?php

use App\Models\GlobalProperty;
use App\Models\Role;
use App\Models\User;
use App\Services\FacilitySettings;
use Database\Seeders\RolesAndModulesSeeder;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);

    // Every test starts configured; these tests exercise the first run.
    GlobalProperty::query()->delete();
    app(FacilitySettings::class)->forget();
});

/**
 * Create a signed-in-ready user holding the given role.
 */
function setupUserWithRole(string $roleSlug): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

    return $user->fresh();
}

/**
 * A valid facility profile submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function facilityProfile(array $overrides = []): array
{
    return [
        'name' => 'Ikeja General Hospital',
        'code' => '25/08/1/1/1/0001',
        'state' => 'Lagos',
        'lga' => 'Ikeja',
        ...$overrides,
    ];
}

test('the login screen still renders before setup', function () {
    $this->get(route('login'))->assertOk();
});

test('an administrator is sent to the wizard until setup is complete', function () {
    $admin = setupUserWithRole('super-administrator');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('setup.show', absolute: false));

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertRedirect(route('setup.show', absolute: false));
});

test('the wizard renders for an administrator', function () {
    $admin = setupUserWithRole('super-administrator');

    $this->actingAs($admin)
        ->get(route('setup.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/Wizard')
            ->where('profile.name', null)
            ->where('profile.completed_at', null)
        );
});

test('other staff are held at the pending page', function () {
    $nurse = setupUserWithRole('nurse');

    $this->actingAs($nurse)
        ->get(route('dashboard'))
        ->assertRedirect(route('setup.pending', absolute: false));

    $this->actingAs($nurse)
        ->get(route('setup.pending'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('setup/Pending'));

    $this->actingAs($nurse)
        ->get(route('setup.show'))
        ->assertForbidden();
});

test('staff can still sign out before setup', function () {
    $nurse = setupUserWithRole('nurse');

    $this->actingAs($nurse)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

test('completing the wizard stores the facility profile and opens the system', function () {
    $admin = setupUserWithRole('super-administrator');

    $this->actingAs($admin)
        ->post(route('setup.store'), facilityProfile())
        ->assertRedirect(route('dashboard', absolute: false));

    $facility = app(FacilitySettings::class);

    expect($facility->isConfigured())->toBeTrue()
        ->and($facility->profile())->toMatchArray([
            'name' => 'Ikeja General Hospital',
            'code' => '25/08/1/1/1/0001',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
        ])
        ->and(GlobalProperty::valueOf(FacilitySettings::KEY_NAME))->toBe('Ikeja General Hospital')
        ->and(GlobalProperty::valueOf(FacilitySettings::KEY_SETUP_COMPLETED_AT))->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('facility.name', 'Ikeja General Hospital')
            ->where('facility.code', '25/08/1/1/1/0001')
        );
});

test('the wizard redirects to the dashboard once setup is complete', function () {
    $admin = setupUserWithRole('super-administrator');
    app(FacilitySettings::class)->complete(facilityProfile());

    $this->actingAs($admin)
        ->get(route('setup.show'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($admin)
        ->get(route('setup.pending'))
        ->assertRedirect(route('dashboard', absolute: false));
});

test('the wizard validates the facility profile', function (array $overrides, string $field) {
    $admin = setupUserWithRole('super-administrator');

    $this->actingAs($admin)
        ->from(route('setup.show'))
        ->post(route('setup.store'), facilityProfile($overrides))
        ->assertRedirect(route('setup.show', absolute: false))
        ->assertSessionHasErrors($field);

    expect(app(FacilitySettings::class)->isConfigured())->toBeFalse();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing code' => [['code' => ''], 'code'],
    'code with spaces' => [['code' => 'LAG 001'], 'code'],
    'unknown state' => [['state' => 'Atlantis'], 'state'],
    'lga outside the state' => [['state' => 'Lagos', 'lga' => 'Kano Municipal'], 'lga'],
]);

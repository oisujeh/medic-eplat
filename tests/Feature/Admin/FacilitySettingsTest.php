<?php

use App\Models\Role;
use App\Models\User;
use App\Services\FacilitySettings;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * Create a user holding the given role.
 */
function facilityAdminWithRole(string $roleSlug): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::where('slug', $roleSlug)->pluck('id'));

    return $user->fresh();
}

test('an administrator sees the facility profile', function () {
    $admin = facilityAdminWithRole('super-administrator');

    $this->actingAs($admin)
        ->get(route('admin.facility.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Facility')
            ->where('profile.name', TestCase::TEST_FACILITY['name'])
            ->where('profile.state', TestCase::TEST_FACILITY['state'])
            ->where('profile.lga', TestCase::TEST_FACILITY['lga'])
            ->where('profile.code', TestCase::TEST_FACILITY['code'])
        );
});

test('an administrator can revise the facility profile', function () {
    $admin = facilityAdminWithRole('super-administrator');

    $this->actingAs($admin)
        ->from(route('admin.facility.edit'))
        ->patch(route('admin.facility.update'), [
            'name' => 'Kano Teaching Hospital',
            'code' => 'KN-TH-01',
            'state' => 'Kano',
            'lga' => 'Kano Municipal',
        ])
        ->assertRedirect(route('admin.facility.edit', absolute: false))
        ->assertSessionHasNoErrors();

    expect(app(FacilitySettings::class)->profile())->toMatchArray([
        'name' => 'Kano Teaching Hospital',
        'code' => 'KN-TH-01',
        'state' => 'Kano',
        'lga' => 'Kano Municipal',
    ]);

    // The revised name is what the rest of the system now shows.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('facility.name', 'Kano Teaching Hospital'));
});

test('the profile carries level, ownership, tier, DHIS2 unit, billing switch and numbering', function () {
    $admin = facilityAdminWithRole('super-administrator');

    $this->actingAs($admin)
        ->from(route('admin.facility.edit'))
        ->patch(route('admin.facility.update'), [
            ...TestCase::TEST_FACILITY,
            'level' => 'secondary',
            'ownership' => 'public',
            'tier_profile' => 'secondary',
            'dhis2_org_unit' => 'a1B2c3D4e5F',
            'billing_enabled' => false,
            'patient_number_prefix' => 'ikgh',
            'patient_number_check_digit' => true,
        ])
        ->assertSessionHasNoErrors();

    $profile = app(FacilitySettings::class)->profile();

    expect($profile)->toMatchArray([
        'level' => 'secondary',
        'ownership' => 'public',
        'tier_profile' => 'secondary',
        'dhis2_org_unit' => 'a1B2c3D4e5F',
        'billing_enabled' => false,
        'patient_number_prefix' => 'IKGH',
        'patient_number_check_digit' => true,
    ]);

    expect(app(FacilitySettings::class)->billingEnabled())->toBeFalse();
});

test('billing is on until a facility switches it off', function () {
    expect(app(FacilitySettings::class)->billingEnabled())->toBeTrue();
});

test('the optional profile fields are validated', function () {
    $admin = facilityAdminWithRole('super-administrator');

    $this->actingAs($admin)
        ->from(route('admin.facility.edit'))
        ->patch(route('admin.facility.update'), [
            ...TestCase::TEST_FACILITY,
            'level' => 'quaternary',
            'dhis2_org_unit' => '123',
            'patient_number_prefix' => 'TOO-LONG',
        ])
        ->assertSessionHasErrors(['level', 'dhis2_org_unit', 'patient_number_prefix']);
});

test('a submission without the optional fields leaves them as they were', function () {
    $admin = facilityAdminWithRole('super-administrator');
    app(FacilitySettings::class)->save([...TestCase::TEST_FACILITY, 'level' => 'tertiary']);

    $this->actingAs($admin)
        ->patch(route('admin.facility.update'), TestCase::TEST_FACILITY)
        ->assertSessionHasNoErrors();

    expect(app(FacilitySettings::class)->profile()['level'])->toBe('tertiary');
});

test('revising the profile rejects an LGA outside the chosen state', function () {
    $admin = facilityAdminWithRole('super-administrator');

    $this->actingAs($admin)
        ->from(route('admin.facility.edit'))
        ->patch(route('admin.facility.update'), [
            'name' => 'Kano Teaching Hospital',
            'code' => 'KN-TH-01',
            'state' => 'Kano',
            'lga' => 'Ikeja',
        ])
        ->assertRedirect(route('admin.facility.edit', absolute: false))
        ->assertSessionHasErrors('lga');

    expect(app(FacilitySettings::class)->profile()['name'])->toBe(TestCase::TEST_FACILITY['name']);
});

test('staff without administration access cannot reach the facility profile', function () {
    $physician = facilityAdminWithRole('physician');

    $this->actingAs($physician)
        ->get(route('admin.facility.edit'))
        ->assertForbidden();

    $this->actingAs($physician)
        ->patch(route('admin.facility.update'), [
            'name' => 'Hijacked',
            'code' => 'X',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
        ])
        ->assertForbidden();
});

test('a profile cached by an older release is rebuilt with the keys it lacks', function () {
    // Before the billing switch and numbering settings existed, the cached
    // profile had only the identity fields. It is cached forever, so an
    // upgraded installation must not keep serving that shape.
    Cache::forever('facility.profile', ['name' => 'Stale', 'state' => 'Lagos', 'lga' => 'Ikeja', 'code' => 'X', 'notice' => null, 'completed_at' => null]);

    $settings = app(FacilitySettings::class);

    expect($settings->billingEnabled())->toBeTrue()
        ->and($settings->profile()['name'])->toBe(TestCase::TEST_FACILITY['name'])
        ->and(Cache::get('facility.profile'))->toHaveKeys(['billing_enabled', 'patient_number_prefix', 'patient_number_check_digit']);
});

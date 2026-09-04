<?php

use App\Models\Role;
use App\Models\User;
use App\Services\FacilitySettings;
use Database\Seeders\RolesAndModulesSeeder;
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

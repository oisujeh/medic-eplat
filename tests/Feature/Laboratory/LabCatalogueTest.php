<?php

use App\Enums\Permission;
use App\Models\LabReferenceRange;
use App\Models\LabTest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LabCompendiumSeeder;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(LabCompendiumSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function catalogueUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('lab staff browse the catalogue and open a test', function () {
    $hb = LabTest::where('code', 'HB')->firstOrFail();
    $scientist = catalogueUser(['laboratory-staff']);

    actingAs($scientist)
        ->get(route('laboratory.catalogue.index', ['q' => 'haemo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Catalogue')
            ->where('canManage', true)
            ->where('tests.0.code', 'HB'));

    actingAs($scientist)
        ->get(route('laboratory.catalogue.show', $hb))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Test')
            ->where('test.code', 'HB')
            ->has('ranges', 0));
});

test('a scientist adds and removes a reference band', function () {
    $hb = LabTest::where('code', 'HB')->firstOrFail();
    $scientist = catalogueUser(['laboratory-staff']);

    expect($scientist->hasPermission(Permission::ManageLabCatalogue))->toBeTrue();

    actingAs($scientist)
        ->post(route('laboratory.catalogue.ranges.store', $hb), [
            'sex' => 'F', 'age_min_days' => 15 * 365, 'age_max_days' => null,
            'low' => 12, 'high' => 15.5, 'critical_low' => 7, 'critical_high' => 20,
        ])
        ->assertSessionHasNoErrors();

    $band = LabReferenceRange::firstOrFail();

    expect($band->sex)->toBe('F')
        ->and($hb->fresh()->rangeFor(30 * 365, 'F'))->toMatchArray(['low' => 12.0, 'high' => 15.5, 'critical_low' => 7.0]);

    actingAs($scientist)
        ->get(route('laboratory.catalogue.show', $hb))
        ->assertInertia(fn ($page) => $page
            ->where('ranges.0.sex_label', 'Female')
            ->where('ranges.0.age_label', '15y and over'));

    actingAs($scientist)
        ->delete(route('laboratory.catalogue.ranges.destroy', [$hb, $band]))
        ->assertSessionHasNoErrors();

    expect(LabReferenceRange::count())->toBe(0);
});

test('a band must make sense before it is saved', function () {
    $hb = LabTest::where('code', 'HB')->firstOrFail();
    $scientist = catalogueUser(['laboratory-staff']);

    actingAs($scientist)
        ->post(route('laboratory.catalogue.ranges.store', $hb), ['sex' => 'X', 'age_min_days' => 0])
        ->assertSessionHasErrors(['sex', 'low']);

    actingAs($scientist)
        ->post(route('laboratory.catalogue.ranges.store', $hb), [
            'age_min_days' => 400, 'age_max_days' => 100, 'low' => 15, 'high' => 12, 'critical_low' => 16, 'critical_high' => 10,
        ])
        ->assertSessionHasErrors(['age_max_days', 'high', 'critical_low', 'critical_high']);
});

test('a physician can read the catalogue but not change it', function () {
    $hb = LabTest::where('code', 'HB')->firstOrFail();
    $physician = catalogueUser(['physician']);

    actingAs($physician)
        ->get(route('laboratory.catalogue.show', $hb))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    actingAs($physician)
        ->post(route('laboratory.catalogue.ranges.store', $hb), ['age_min_days' => 0, 'low' => 1, 'high' => 2])
        ->assertForbidden();
});

test('a band belongs to the test it is removed from', function () {
    $hb = LabTest::where('code', 'HB')->firstOrFail();
    $wbc = LabTest::where('code', 'WBC')->firstOrFail();
    $band = $hb->referenceRanges()->create(['age_min_days' => 0, 'low' => 1, 'high' => 2]);

    actingAs(catalogueUser(['laboratory-staff']))
        ->delete(route('laboratory.catalogue.ranges.destroy', [$wbc, $band]))
        ->assertNotFound();

    expect(LabReferenceRange::count())->toBe(1);
});

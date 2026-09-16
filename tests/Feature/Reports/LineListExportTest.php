<?php

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportRegistry;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * A member of staff who can open the reports module (the CMD role), with
 * or without the line-list export permission.
 */
function reportsStaff(bool $canExportLineLists): User
{
    $role = Role::where('slug', 'chief-medical-director')->firstOrFail();

    if (! $canExportLineLists) {
        $role->syncPermissions(array_values(array_filter(
            Permission::cases(),
            fn (Permission $p) => $p !== Permission::ExportLineLists && in_array($role->slug, $p->defaultRoles(), true),
        )));
    }

    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

test('every line list is a report the registry knows', function () {
    $registry = app(ReportRegistry::class);

    foreach (ReportRegistry::LINE_LISTS as $key) {
        expect($registry->find($key))->not->toBeNull($key);
    }
});

test('exporting a line list needs the export permission', function () {
    $viewer = reportsStaff(canExportLineLists: false);
    expect($viewer->hasPermission(Permission::ExportLineLists))->toBeFalse();

    actingAs($viewer)
        ->get(route('reports.run', 'patient-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canExport', false));

    actingAs($viewer)
        ->get(route('reports.export', 'patient-register'))
        ->assertForbidden();
});

test('aggregates export without the line-list permission', function () {
    actingAs(reportsStaff(canExportLineLists: false))
        ->get(route('reports.export', 'diagnoses-summary'))
        ->assertOk();
});

test('the permission unlocks the export', function () {
    $exporter = reportsStaff(canExportLineLists: true);

    actingAs($exporter)
        ->get(route('reports.run', 'patient-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canExport', true));

    $response = actingAs($exporter)->get(route('reports.export', 'patient-register'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('records officers hold the export permission by default', function () {
    $officer = User::factory()->create();
    $officer->roles()->sync(Role::where('slug', 'records-officer')->pluck('id'));

    expect($officer->fresh()->hasPermission(Permission::ExportLineLists))->toBeTrue();
});

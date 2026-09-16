<?php

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServiceCharge;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServiceChargesSeeder;
use Database\Seeders\WardsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServiceChargesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function wardAdmin(array $roleSlugs = ['nurse']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('a ward can be created with its first beds', function () {
    $charge = ServiceCharge::where('code', 'BED-GEN')->firstOrFail();

    actingAs(wardAdmin())
        ->post(route('admissions.wards.store'), [
            'name' => 'Male Medical Ward',
            'code' => 'mmw',
            'type' => 'male',
            'bed_service_charge_id' => $charge->id,
            'initial_beds' => 6,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $ward = Ward::firstOrFail();

    expect($ward->code)->toBe('MMW')
        ->and($ward->type->value)->toBe('male')
        ->and($ward->bed_service_charge_id)->toBe($charge->id)
        ->and($ward->beds()->count())->toBe(6)
        ->and($ward->beds()->pluck('label')->all())->toBe(['Bed 1', 'Bed 2', 'Bed 3', 'Bed 4', 'Bed 5', 'Bed 6']);
});

test('ward codes must be unique', function () {
    Ward::factory()->create(['code' => 'MMW']);

    actingAs(wardAdmin())
        ->post(route('admissions.wards.store'), ['name' => 'Another', 'code' => 'mmw', 'type' => 'general'])
        ->assertSessionHasErrors('code');
});

test('the bed board shows every bed and its occupant', function () {
    $ward = Ward::factory()->withBeds(3)->create();

    actingAs(wardAdmin())
        ->get(route('admissions.wards.show', $ward))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admissions/Ward')
            ->where('ward.id', $ward->id)
            ->where('ward.total', 3)
            ->has('beds', 3)
            ->where('beds.0.occupant', null)
        );
});

test('adding beds continues the numbering', function () {
    $ward = Ward::factory()->withBeds(2)->create();

    actingAs(wardAdmin())
        ->post(route('admissions.wards.beds.store', $ward), ['count' => 3])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ward->beds()->pluck('label')->all())->toBe(['Bed 1', 'Bed 2', 'Bed 3', 'Bed 4', 'Bed 5']);
});

test('a bed can be taken out of service and brought back', function () {
    $bed = Bed::factory()->create();

    actingAs(wardAdmin())
        ->patch(route('admissions.beds.update', $bed), ['status' => 'out_of_service', 'notes' => 'Mattress replacement'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($bed->fresh()->status)->toBe(BedStatus::OutOfService)
        ->and($bed->fresh()->notes)->toBe('Mattress replacement');

    actingAs(wardAdmin())
        ->patch(route('admissions.beds.update', $bed), ['status' => 'available'])
        ->assertSessionHasNoErrors();

    expect($bed->fresh()->status)->toBe(BedStatus::Available);
});

test('an occupied bed cannot be changed', function () {
    $bed = Bed::factory()->create(['status' => BedStatus::Occupied]);

    actingAs(wardAdmin())
        ->patch(route('admissions.beds.update', $bed), ['status' => 'out_of_service'])
        ->assertSessionHasErrors('status');

    expect($bed->fresh()->status)->toBe(BedStatus::Occupied);
});

test('a ward can be updated and deactivated', function () {
    $ward = Ward::factory()->create();

    actingAs(wardAdmin())
        ->patch(route('admissions.wards.update', $ward), [
            'name' => 'Renamed Ward',
            'code' => $ward->code,
            'type' => 'surgical',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $ward->refresh();

    expect($ward->name)->toBe('Renamed Ward')
        ->and($ward->type->value)->toBe('surgical')
        ->and($ward->is_active)->toBeFalse();
});

test('the wards seeder is idempotent and keeps existing beds', function () {
    $this->seed(WardsSeeder::class);

    $before = Bed::count();
    Ward::where('code', 'ICU')->firstOrFail()->beds()->first()->update(['status' => BedStatus::OutOfService]);

    $this->seed(WardsSeeder::class);

    expect(Ward::count())->toBe(8)
        ->and(Bed::count())->toBe($before)
        ->and(Ward::where('code', 'ICU')->firstOrFail()->beds()->first()->status)->toBe(BedStatus::OutOfService)
        ->and(Ward::where('code', 'ICU')->firstOrFail()->bedCharge?->code)->toBe('BED-ICU');
});

test('staff outside the module cannot manage wards', function () {
    actingAs(wardAdmin(['cashier']))
        ->post(route('admissions.wards.store'), ['name' => 'X', 'code' => 'X', 'type' => 'general'])
        ->assertForbidden();

    expect(Patient::count())->toBe(0);
});

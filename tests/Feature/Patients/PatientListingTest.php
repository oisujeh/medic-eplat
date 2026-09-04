<?php

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function listingUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('staff with patient records access can view the list', function () {
    Patient::factory()->count(3)->create();

    actingAs(listingUser(['records-officer']))
        ->get(route('patients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/Index')
            ->has('patients.data', 3)
        );
});

test('staff without patient records access are forbidden from the list', function () {
    actingAs(listingUser(['laboratory-staff']))
        ->get(route('patients.index'))
        ->assertForbidden();
});

test('the list can be searched by name', function () {
    Patient::factory()->create(['surname' => 'Okonkwo', 'first_name' => 'Chidi']);
    Patient::factory()->create(['surname' => 'Balogun', 'first_name' => 'Tunde']);

    actingAs(listingUser(['records-officer']))
        ->get(route('patients.index', ['search' => 'Okonkwo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('patients.data', 1)
            ->where('patients.data.0.name', fn ($name) => str_contains($name, 'Okonkwo'))
        );
});

test('a patient profile can be viewed', function () {
    $patient = Patient::factory()->create(['surname' => 'Adeyemi', 'first_name' => 'Bola']);

    actingAs(listingUser(['physician']))
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/Show')
            ->where('patient.file_number', $patient->file_number)
            ->where('patient.surname', 'Adeyemi')
        );
});

test('staff without patient records access are forbidden from a profile', function () {
    $patient = Patient::factory()->create();

    actingAs(listingUser(['pharmacy-staff']))
        ->get(route('patients.show', $patient))
        ->assertForbidden();
});

test('the register button is only offered to staff who can register', function () {
    actingAs(listingUser(['physician']))
        ->get(route('patients.index'))
        ->assertInertia(fn ($page) => $page->where('canRegister', false));

    actingAs(listingUser(['records-officer']))
        ->get(route('patients.index'))
        ->assertInertia(fn ($page) => $page->where('canRegister', true));
});

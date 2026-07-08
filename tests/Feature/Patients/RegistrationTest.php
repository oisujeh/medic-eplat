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
function patientUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validPatientPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Mrs',
        'surname' => 'Chukwu',
        'first_name' => 'Ngozi',
        'other_names' => 'Amaka',
        'date_of_birth' => '1990-05-12',
        'sex' => 'F',
        'marital_status' => 'Married',
        'phone' => '08031234567',
        'email' => 'ngozi@example.com',
        'address' => '12 Marina Road',
        'nationality' => 'Nigerian',
        'state' => 'Lagos',
        'lga' => 'Ikeja',
        'next_of_kin_name' => 'Emeka Chukwu',
        'next_of_kin_relationship' => 'Spouse',
        'next_of_kin_phone' => '08039998877',
        'coverage' => 'private',
        'is_transfer' => false,
        'visit_category' => 'Outpatient',
        'outpatient_service' => 'Clinical Consultation & Diagnosis',
    ], $overrides);
}

test('records officer can view the registration form', function () {
    actingAs(patientUser(['records-officer']))
        ->get(route('patients.register'))
        ->assertOk();
});

test('staff without the registration module are forbidden', function () {
    actingAs(patientUser(['laboratory-staff']))
        ->get(route('patients.register'))
        ->assertForbidden();

    actingAs(patientUser(['laboratory-staff']))
        ->post(route('patients.store'), validPatientPayload())
        ->assertForbidden();
});

test('a records officer can register a patient', function () {
    $officer = patientUser(['records-officer']);

    actingAs($officer)
        ->post(route('patients.store'), validPatientPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('patients.show', Patient::first()));

    $patient = Patient::first();

    expect($patient)->not->toBeNull();
    expect($patient->surname)->toBe('Chukwu');
    expect($patient->state)->toBe('Lagos');
    expect($patient->lga)->toBe('Ikeja');
    expect($patient->registered_by)->toBe($officer->id);
    expect($patient->file_number)->toBe('MEP/'.now()->year.'/'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT));
});

test('an LGA must belong to the selected state', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['state' => 'Lagos', 'lga' => 'Nsukka']))
        ->assertSessionHasErrors('lga');

    expect(Patient::count())->toBe(0);
});

test('an unknown state is rejected', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['state' => 'Atlantis', 'lga' => 'Ikeja']))
        ->assertSessionHasErrors('state');
});

test('surname and first name are required', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['surname' => '', 'first_name' => '']))
        ->assertSessionHasErrors(['surname', 'first_name']);
});

test('HMO patients must have a provider', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['coverage' => 'hmo', 'hmo_name' => '']))
        ->assertSessionHasErrors('hmo_name');
});

test('outpatient visits require a service point', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['visit_category' => 'Outpatient', 'outpatient_service' => '']))
        ->assertSessionHasErrors('outpatient_service');
});

test('transfer patients must record the referring facility', function () {
    actingAs(patientUser(['records-officer']))
        ->post(route('patients.store'), validPatientPayload(['is_transfer' => true, 'transfer_from' => '']))
        ->assertSessionHasErrors('transfer_from');
});

test('file numbers are unique and sequential', function () {
    $officer = patientUser(['records-officer']);

    actingAs($officer)->post(route('patients.store'), validPatientPayload());
    actingAs($officer)->post(route('patients.store'), validPatientPayload(['surname' => 'Bello']));

    expect(Patient::pluck('file_number')->unique())->toHaveCount(2);
});

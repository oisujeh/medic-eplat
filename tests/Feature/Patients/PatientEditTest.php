<?php

use App\Models\Patient;
use App\Models\Payer;
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
function editorWithRoles(array $roleSlugs = ['records-officer']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A complete edit payload built from the patient's current record.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function editPayload(Patient $patient, array $overrides = []): array
{
    return array_merge([
        'title' => $patient->title,
        'surname' => $patient->surname,
        'first_name' => $patient->first_name,
        'other_names' => $patient->other_names,
        'date_of_birth' => $patient->date_of_birth?->toDateString(),
        'sex' => $patient->sex,
        'marital_status' => $patient->marital_status,
        'phone' => $patient->phone,
        'email' => $patient->email,
        'address' => $patient->address,
        'nationality' => $patient->nationality ?? 'Nigerian',
        'state' => 'Lagos',
        'lga' => 'Ikeja',
        'next_of_kin_name' => $patient->next_of_kin_name,
        'next_of_kin_relationship' => $patient->next_of_kin_relationship,
        'next_of_kin_phone' => $patient->next_of_kin_phone,
        'coverage' => 'private',
        'is_transfer' => false,
        'visit_category' => 'Outpatient',
        'outpatient_service' => 'Clinical Consultation & Diagnosis',
    ], $overrides);
}

test('a records officer can open the edit form', function () {
    $patient = Patient::factory()->create();

    actingAs(editorWithRoles())
        ->get(route('patients.edit', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/Edit')
            ->where('patient.id', $patient->id)
            ->where('patient.surname', $patient->surname)
            ->has('options.payers')
        );
});

test('clinical staff cannot edit demographics', function () {
    $patient = Patient::factory()->create();

    actingAs(editorWithRoles(['physician']))
        ->get(route('patients.edit', $patient))
        ->assertForbidden();

    actingAs(editorWithRoles(['physician']))
        ->patch(route('patients.update', $patient), editPayload($patient, ['surname' => 'Hijacked']))
        ->assertForbidden();

    expect($patient->fresh()->surname)->not->toBe('Hijacked');
});

test('the profile offers editing only to staff who can register', function () {
    $patient = Patient::factory()->create();

    actingAs(editorWithRoles())
        ->get(route('patients.show', $patient))
        ->assertInertia(fn ($page) => $page->where('canEdit', true));

    actingAs(editorWithRoles(['physician']))
        ->get(route('patients.show', $patient))
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});

test('demographics are updated and the file number is kept', function () {
    $patient = Patient::factory()->create();
    $fileNumber = $patient->file_number;

    actingAs(editorWithRoles())
        ->patch(route('patients.update', $patient), editPayload($patient, [
            'surname' => 'Okonkwo',
            'phone' => '08099887766',
            'state' => 'Kano',
            'lga' => 'Kano Municipal',
            'next_of_kin_name' => 'Ada Okonkwo',
            'next_of_kin_relationship' => 'Spouse',
        ]))
        ->assertRedirect(route('patients.show', $patient, absolute: false))
        ->assertSessionHasNoErrors();

    $patient->refresh();

    expect($patient->surname)->toBe('Okonkwo')
        ->and($patient->phone)->toBe('08099887766')
        ->and($patient->state)->toBe('Kano')
        ->and($patient->lga)->toBe('Kano Municipal')
        ->and($patient->next_of_kin_name)->toBe('Ada Okonkwo')
        ->and($patient->file_number)->toBe($fileNumber);
});

test('switching a patient to HMO coverage links the payer', function () {
    $patient = Patient::factory()->create(['coverage' => 'private']);
    $nhia = Payer::factory()->nhia()->create();

    actingAs(editorWithRoles())
        ->patch(route('patients.update', $patient), editPayload($patient, [
            'coverage' => 'hmo',
            'payer_id' => $nhia->id,
            'hmo_number' => 'NHIA-0099',
            'hmo_plan' => 'Formal sector',
            'hmo_expires_at' => '2027-06-30',
        ]))
        ->assertSessionHasNoErrors();

    $patient->refresh();

    expect($patient->coverage)->toBe('hmo')
        ->and($patient->payer_id)->toBe($nhia->id)
        ->and($patient->hmo_name)->toBe($nhia->name)
        ->and($patient->hmo_number)->toBe('NHIA-0099')
        ->and($patient->hmo_expires_at->toDateString())->toBe('2027-06-30');
});

test('switching back to private clears the payer details', function () {
    $nhia = Payer::factory()->nhia()->create();
    $patient = Patient::factory()->create([
        'coverage' => 'hmo',
        'payer_id' => $nhia->id,
        'hmo_name' => $nhia->name,
        'hmo_number' => 'NHIA-0099',
        'hmo_plan' => 'Formal sector',
    ]);

    actingAs(editorWithRoles())
        ->patch(route('patients.update', $patient), editPayload($patient, ['coverage' => 'private']))
        ->assertSessionHasNoErrors();

    $patient->refresh();

    expect($patient->coverage)->toBe('private')
        ->and($patient->payer_id)->toBeNull()
        ->and($patient->hmo_name)->toBeNull()
        ->and($patient->hmo_number)->toBeNull()
        ->and($patient->hmo_plan)->toBeNull();
});

test('a deactivated payer stays selectable for the patient already on it', function () {
    $retired = Payer::factory()->create(['is_active' => false]);
    $active = Payer::factory()->create();
    $patient = Patient::factory()->create(['coverage' => 'hmo', 'payer_id' => $retired->id, 'hmo_name' => $retired->name]);

    actingAs(editorWithRoles())
        ->get(route('patients.edit', $patient))
        ->assertInertia(fn ($page) => $page
            ->has('options.payers', 2)
            ->where('patient.payer_id', (string) $retired->id)
        );

    actingAs(editorWithRoles())
        ->get(route('patients.edit', Patient::factory()->create()))
        ->assertInertia(fn ($page) => $page
            ->has('options.payers', 1)
            ->where('options.payers.0.id', $active->id)
        );
});

test('edits are validated like registrations', function () {
    $patient = Patient::factory()->create();

    actingAs(editorWithRoles())
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), editPayload($patient, [
            'surname' => '',
            'state' => 'Lagos',
            'lga' => 'Kano Municipal',
            'coverage' => 'hmo',
        ]))
        ->assertRedirect(route('patients.edit', $patient, absolute: false))
        ->assertSessionHasErrors(['surname', 'lga', 'hmo_name']);
});

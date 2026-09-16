<?php

use App\Enums\Permission;
use App\Models\AuditLog;
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
function restrictedUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('an ordinary record opens as before', function () {
    $patient = Patient::factory()->create();

    actingAs(restrictedUser(['nurse']))
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('patients/Show'));
});

test('a restricted record shows only its cover to staff without the permission', function () {
    $patient = Patient::factory()->restricted('VIP')->create();

    actingAs(restrictedUser(['nurse']))
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/Restricted')
            ->where('patient.file_number', $patient->file_number)
            ->where('patient.restricted_reason', 'VIP')
            ->missing('patient.full_name')
        );
});

test('staff holding the break-glass permission open a restricted record outright', function () {
    $patient = Patient::factory()->restricted()->create();
    $physician = restrictedUser(['physician']);

    expect($physician->hasPermission(Permission::BreakGlass))->toBeTrue();

    actingAs($physician)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/Show')
            ->where('patient.is_restricted', true)
        );
});

test('a logged reason unlocks a restricted record for the session', function () {
    $patient = Patient::factory()->restricted()->create();
    $nurse = restrictedUser(['nurse']);

    actingAs($nurse)
        ->post(route('patients.break-glass', $patient), ['reason' => 'Patient collapsed in the ward; chart needed now.'])
        ->assertRedirect(route('patients.show', $patient, absolute: false))
        ->assertSessionHasNoErrors();

    $entry = AuditLog::query()->where('action', AuditLog::ACTION_BREAK_GLASS)->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($nurse->id)
        ->and($entry->patient_id)->toBe($patient->id)
        ->and($entry->reason)->toBe('Patient collapsed in the ward; chart needed now.');

    actingAs($nurse)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('patients/Show'));
});

test('a break-glass request needs a real reason', function () {
    $patient = Patient::factory()->restricted()->create();

    actingAs(restrictedUser(['nurse']))
        ->post(route('patients.break-glass', $patient), ['reason' => 'why'])
        ->assertSessionHasErrors('reason');

    expect(AuditLog::query()->where('action', AuditLog::ACTION_BREAK_GLASS)->exists())->toBeFalse();
});

test('break-glass is not offered on an ordinary record', function () {
    $patient = Patient::factory()->create();

    actingAs(restrictedUser(['nurse']))
        ->post(route('patients.break-glass', $patient), ['reason' => 'No need, but trying anyway.'])
        ->assertNotFound();
});

test('a records officer can register a restricted patient with a reason', function () {
    $officer = restrictedUser(['records-officer']);

    actingAs($officer)
        ->post(route('patients.store'), [
            'surname' => 'Bello', 'first_name' => 'Aisha', 'sex' => 'F', 'nationality' => 'Nigerian',
            'state' => 'Lagos', 'lga' => 'Ikeja', 'coverage' => 'private', 'visit_category' => 'Outpatient',
            'outpatient_service' => 'Clinical Consultation & Diagnosis',
            'is_restricted' => true, 'restricted_reason' => 'Staff member',
        ])
        ->assertSessionHasNoErrors();

    expect(Patient::where('surname', 'Bello')->first()->is_restricted)->toBeTrue();

    actingAs($officer)
        ->post(route('patients.store'), [
            'surname' => 'Bello', 'first_name' => 'Musa', 'sex' => 'M', 'nationality' => 'Nigerian',
            'state' => 'Lagos', 'lga' => 'Ikeja', 'coverage' => 'private', 'visit_category' => 'Outpatient',
            'outpatient_service' => 'Clinical Consultation & Diagnosis',
            'is_restricted' => true,
        ])
        ->assertSessionHasErrors('restricted_reason');
});

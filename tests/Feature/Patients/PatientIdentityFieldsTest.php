<?php

use App\Enums\PatientStatus;
use App\Enums\RelationshipType;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientRelationship;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function identityFieldsPayload(array $overrides = []): array
{
    return [
        'surname' => 'Okafor', 'first_name' => 'Chinedu', 'sex' => 'M', 'nationality' => 'Nigerian',
        'state' => 'Lagos', 'lga' => 'Ikeja', 'coverage' => 'private', 'visit_category' => 'Outpatient',
        'outpatient_service' => 'Clinical Consultation & Diagnosis',
        ...$overrides,
    ];
}

function identityFieldsOfficer(): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::where('slug', 'records-officer')->pluck('id'));

    return $user->fresh();
}

test('a new patient starts active, dated today, with a phonetic key', function () {
    actingAs(identityFieldsOfficer())
        ->post(route('patients.store'), identityFieldsPayload())
        ->assertSessionHasNoErrors();

    $patient = Patient::where('surname', 'Okafor')->firstOrFail();

    expect($patient->status)->toBe(PatientStatus::Active)
        ->and($patient->registered_at?->isToday())->toBeTrue()
        ->and($patient->phonetic_key)->toBe(Patient::phoneticKeyFor('Okafor', 'Chinedu'))
        ->and($patient->phonetic_key)->not->toBeEmpty();
});

test('names that sound alike share a phonetic key', function () {
    expect(Patient::phoneticKeyFor('Okafor', 'Chinedu'))->toBe(Patient::phoneticKeyFor('Okafore', 'Chinedou'))
        ->and(Patient::phoneticKeyFor('Okafor', 'Chinedu'))->not->toBe(Patient::phoneticKeyFor('Bello', 'Musa'));
});

test('retrospective registration keeps the date on the paper register', function () {
    actingAs(identityFieldsOfficer())
        ->post(route('patients.store'), identityFieldsPayload(['registered_at' => '2024-03-15']))
        ->assertSessionHasNoErrors();

    $patient = Patient::where('surname', 'Okafor')->firstOrFail();

    expect($patient->registered_at?->toDateString())->toBe('2024-03-15')
        ->and($patient->created_at?->isToday())->toBeTrue();
});

test('a registration date in the future is refused', function () {
    actingAs(identityFieldsOfficer())
        ->post(route('patients.store'), identityFieldsPayload(['registered_at' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('registered_at');
});

test('phone owner, alternate phone and SMS consent are captured', function () {
    actingAs(identityFieldsOfficer())
        ->post(route('patients.store'), identityFieldsPayload([
            'phone' => '08031234567', 'phone_owner' => 'spouse', 'phone_alternate' => '08091234567', 'sms_consent' => true,
        ]))
        ->assertSessionHasNoErrors();

    $patient = Patient::where('surname', 'Okafor')->firstOrFail();

    expect($patient->phone_owner)->toBe('spouse')
        ->and($patient->phone_alternate)->toBe('08091234567')
        ->and($patient->sms_consent)->toBeTrue();

    actingAs(identityFieldsOfficer())
        ->post(route('patients.store'), identityFieldsPayload(['phone_owner' => 'neighbour']))
        ->assertSessionHasErrors('phone_owner');
});

test('a status change is dated and carries its reason', function () {
    $patient = Patient::factory()->create();

    $patient->changeStatus(PatientStatus::TransferredOut, 'Moved to Kano', 'Aminu Kano Teaching Hospital');

    $patient->refresh();

    expect($patient->status)->toBe(PatientStatus::TransferredOut)
        ->and($patient->status_changed_at)->not->toBeNull()
        ->and($patient->status_reason)->toBe('Moved to Kano')
        ->and($patient->transferred_to)->toBe('Aminu Kano Teaching Hospital');

    $patient->changeStatus(PatientStatus::LostToFollowUp, 'No contact for 90 days');

    expect($patient->fresh()->transferred_to)->toBeNull();
});

test('records staff change a patient\'s status from the profile with a reason', function () {
    $patient = Patient::factory()->create();
    $officer = identityFieldsOfficer();

    actingAs($officer)
        ->patch(route('patients.status', $patient), ['status' => 'transferred_out', 'reason' => 'Relocated'])
        ->assertSessionHasErrors('transferred_to');

    actingAs($officer)
        ->patch(route('patients.status', $patient), [
            'status' => 'transferred_out', 'reason' => 'Relocated', 'transferred_to' => 'Kano',
        ])
        ->assertSessionHasNoErrors();

    $patient->refresh();

    expect($patient->status)->toBe(PatientStatus::TransferredOut)
        ->and($patient->transferred_to)->toBe('Kano')
        ->and(AuditLog::query()->where('patient_id', $patient->id)->where('reason', 'Relocated')->exists())->toBeTrue();

    // Death is never set by hand.
    actingAs($officer)
        ->patch(route('patients.status', $patient), ['status' => 'deceased', 'reason' => 'Died'])
        ->assertSessionHasErrors('status');

    // And staff outside registration cannot change it at all.
    $nurse = User::factory()->create();
    $nurse->roles()->sync(Role::where('slug', 'nurse')->pluck('id'));

    actingAs($nurse->fresh())
        ->patch(route('patients.status', $patient), ['status' => 'active', 'reason' => 'Back'])
        ->assertForbidden();
});

test('records staff link and unlink patients from the profile', function () {
    $child = Patient::factory()->create();
    $mother = Patient::factory()->create(['sex' => 'F']);
    $officer = identityFieldsOfficer();

    actingAs($officer)
        ->get(route('patients.relationships.search', [$child, 'q' => $mother->surname]))
        ->assertOk()
        ->assertJsonPath('patients.0.id', $mother->id);

    actingAs($officer)
        ->post(route('patients.relationships.store', $child), ['related_patient_id' => $child->id, 'type' => 'mother'])
        ->assertSessionHasErrors('related_patient_id');

    actingAs($officer)
        ->post(route('patients.relationships.store', $child), ['related_patient_id' => $mother->id, 'type' => 'mother'])
        ->assertSessionHasNoErrors();

    actingAs($officer)
        ->get(route('patients.show', $child))
        ->assertInertia(fn ($page) => $page
            ->where('relationships.0.type_label', 'Mother')
            ->where('relationships.0.file_number', $mother->file_number));

    $link = PatientRelationship::where('patient_id', $child->id)->firstOrFail();

    actingAs($officer)
        ->delete(route('patients.relationships.destroy', [$child, $link]))
        ->assertSessionHasNoErrors();

    expect(PatientRelationship::count())->toBe(0);
});

test('linking two patients writes both directions', function () {
    $mother = Patient::factory()->create(['sex' => 'F']);
    $child = Patient::factory()->create();

    PatientRelationship::link($child, $mother, RelationshipType::Mother);

    expect($child->relationships()->first()?->type)->toBe(RelationshipType::Mother)
        ->and($mother->relationships()->first()?->type)->toBe(RelationshipType::Child)
        ->and($mother->relationships()->first()?->related_patient_id)->toBe($child->id);

    // Linking again is a no-op, not a duplicate.
    PatientRelationship::link($child, $mother, RelationshipType::Mother);

    expect(PatientRelationship::count())->toBe(2);
});

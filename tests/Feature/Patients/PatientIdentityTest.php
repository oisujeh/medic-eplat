<?php

use App\Enums\IdentifierType;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Death;
use App\Models\Delivery;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\Role;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function identityUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function identityPayload(array $overrides = []): array
{
    return array_merge([
        'surname' => 'Okonkwo', 'first_name' => 'Ifeanyi', 'sex' => 'M', 'date_of_birth' => '1988-02-14',
        'nationality' => 'Nigerian', 'state' => 'Lagos', 'lga' => 'Ikeja', 'ward' => 'Ojodu',
        'phone' => '0803 555 0101', 'nin' => '12345678901', 'coverage' => 'private', 'is_transfer' => false,
        'visit_category' => 'Outpatient', 'outpatient_service' => 'Clinical Consultation & Diagnosis',
    ], $overrides);
}

// ---------------------------------------------------------- The identifier index

test('registering a patient indexes the file number, NIN and phone', function () {
    actingAs(identityUser(['records-officer']))
        ->post(route('patients.store'), identityPayload())
        ->assertSessionHasNoErrors();

    $patient = Patient::latest('id')->firstOrFail();
    $index = $patient->identifiers->mapWithKeys(fn ($i) => [$i->type->value => $i->value]);

    expect($index[IdentifierType::FileNumber->value])->toBe($patient->file_number)
        ->and($index[IdentifierType::Nin->value])->toBe('12345678901')
        ->and($index[IdentifierType::Phone->value])->toBe('08035550101')
        ->and($patient->ward)->toBe('Ojodu')
        ->and($patient->dob_estimated)->toBeFalse();
});

test('the index follows the record when an identifier changes', function () {
    $patient = Patient::factory()->create(['phone' => '08010000000', 'hmo_number' => 'HMO-1', 'coverage' => 'hmo', 'hmo_name' => 'Hygeia HMO']);

    expect($patient->identifiers()->where('type', 'phone')->value('value'))->toBe('08010000000');

    $patient->update(['phone' => '08020000000', 'hmo_number' => null]);

    expect($patient->identifiers()->where('type', 'phone')->pluck('value')->all())->toBe(['08020000000'])
        ->and($patient->identifiers()->where('type', 'hmo_number')->exists())->toBeFalse();
});

test('the patient list finds a patient by NIN', function () {
    $patient = Patient::factory()->create(['nin' => '99988877766']);
    Patient::factory()->create();

    actingAs(identityUser(['records-officer']))
        ->get(route('patients.index', ['search' => '99988877766']))
        ->assertInertia(fn ($page) => $page
            ->where('patients.data', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $patient->id));
});

// ---------------------------------------------------------- Search before create

test('a NIN already on record blocks a second registration and names the record', function () {
    $existing = Patient::factory()->create(['nin' => '12345678901']);

    actingAs(identityUser(['records-officer']))
        ->from(route('patients.register'))
        ->post(route('patients.store'), identityPayload())
        ->assertSessionHasErrors('nin');

    expect(session('errors')->first('nin'))->toContain($existing->file_number)
        ->and(Patient::count())->toBe(1);
});

test('editing a record keeps its own NIN', function () {
    $patient = Patient::factory()->create(['nin' => '12345678901', 'coverage' => 'private', 'hmo_name' => null, 'hmo_number' => null]);

    actingAs(identityUser(['records-officer']))
        ->patch(route('patients.update', $patient), identityPayload([
            'surname' => $patient->surname, 'first_name' => $patient->first_name, 'sex' => $patient->sex,
            'state' => $patient->state, 'lga' => $patient->lga, 'dob_estimated' => true,
        ]))
        ->assertSessionHasNoErrors();

    expect($patient->fresh()->dob_estimated)->toBeTrue();
});

test('the lookup lists people the typed details could already describe, strongest first', function () {
    $byNin = Patient::factory()->create(['nin' => '11122233344', 'surname' => 'Bello', 'first_name' => 'Musa']);
    $byNameAndDob = Patient::factory()->create(['surname' => 'Adeyemi', 'first_name' => 'Tunde', 'date_of_birth' => '1990-01-01']);
    $byName = Patient::factory()->create(['surname' => 'Adeyemi', 'first_name' => 'Tunde', 'date_of_birth' => '1975-06-30']);
    $byPhone = Patient::factory()->create(['phone' => '08031112222', 'surname' => 'Umar', 'first_name' => 'Zainab']);
    Patient::factory()->create(['surname' => 'Nobody', 'first_name' => 'Else']);

    $officer = identityUser(['records-officer']);

    $response = actingAs($officer)->getJson(route('patients.lookup', [
        'surname' => 'Adeyemi', 'first_name' => 'Tunde', 'date_of_birth' => '1990-01-01', 'phone' => '0803 111 2222', 'nin' => '11122233344',
    ]));

    $response->assertOk();
    $rows = collect($response->json());

    expect($rows->pluck('id')->all())->toContain($byNin->id, $byNameAndDob->id, $byName->id, $byPhone->id)
        ->and($rows->first()['id'])->toBe($byNin->id)
        ->and($rows->first()['level'])->toBe('auto')
        ->and($rows->firstWhere('id', $byNin->id)['reasons'])->toContain('Same NIN')
        ->and($rows->firstWhere('id', $byNameAndDob->id)['reasons'])->toContain('Same surname', 'Same first name', 'Same date of birth')
        ->and($rows->firstWhere('id', $byNameAndDob->id)['strong'])->toBeTrue()
        ->and($rows->firstWhere('id', $byName->id)['strong'])->toBeFalse()
        ->and($rows->firstWhere('id', $byPhone->id)['reasons'])->toContain('Same phone number');

    // The record being edited is never offered as a match for itself.
    actingAs($officer)
        ->getJson(route('patients.lookup', ['nin' => '11122233344', 'except' => $byNin->id]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('the lookup needs the registration module', function () {
    actingAs(identityUser(['laboratory-staff']))
        ->getJson(route('patients.lookup', ['nin' => '11122233344']))
        ->assertForbidden();
});

// ---------------------------------------------------------- Parent link and deaths

test('a registered newborn is linked to its mother', function () {
    $mother = Patient::factory()->create(['sex' => 'F']);
    $pregnancy = Pregnancy::factory()->create(['patient_id' => $mother->id]);
    $midwife = identityUser(['nurse']);

    actingAs($midwife)->post(route('maternity.delivery.store', $pregnancy), [
        'delivered_at' => now()->subHour()->format('Y-m-d H:i'),
        'mode' => 'svd',
        'maternal_outcome' => 'well',
        'births' => [['outcome' => 'live', 'sex' => 'F', 'weight_grams' => 3100]],
    ])->assertSessionHasNoErrors();

    $birth = $pregnancy->fresh()->delivery->births()->firstOrFail();

    actingAs($midwife)->post(route('maternity.births.register', $birth))->assertSessionHasNoErrors();

    $baby = Patient::findOrFail($birth->fresh()->newborn_patient_id);

    expect($baby->mother_patient_id)->toBe($mother->id)
        ->and($mother->children()->pluck('id')->all())->toBe([$baby->id])
        ->and($baby->dob_estimated)->toBeFalse();
});

test('a deceased discharge writes the death record and the chart shows it', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::factory()->create(['ward_id' => $ward->id]);
    $patient = Patient::factory()->create();
    $admission = Admission::factory()->create(['patient_id' => $patient->id, 'ward_id' => $ward->id, 'bed_id' => $bed->id, 'status' => 'admitted', 'admitted_at' => now()->subDay()]);
    $bed->update(['status' => 'occupied']);

    $physician = identityUser(['physician']);

    actingAs($physician)
        ->post(route('admissions.discharge', $admission), ['discharge_type' => 'deceased', 'discharge_summary' => 'Died of septic shock.'])
        ->assertSessionHasNoErrors();

    $death = Death::where('patient_id', $patient->id)->firstOrFail();

    expect($death->source_type)->toBe(Admission::class)
        ->and($death->source_id)->toBe($admission->id)
        ->and($death->certified_by)->toBe($physician->id)
        ->and($patient->fresh()->isDeceased())->toBeTrue();

    actingAs($physician)
        ->get(route('patients.show', $patient))
        ->assertInertia(fn ($page) => $page->where('patient.deceased_at', fn ($v) => $v !== null));
});

test('a maternal death at delivery writes the death record once', function () {
    $mother = Patient::factory()->create(['sex' => 'F']);
    $pregnancy = Pregnancy::factory()->create(['patient_id' => $mother->id]);

    actingAs(identityUser(['nurse']))->post(route('maternity.delivery.store', $pregnancy), [
        'delivered_at' => now()->subHour()->format('Y-m-d H:i'),
        'mode' => 'svd',
        'maternal_outcome' => 'deceased',
        'births' => [['outcome' => 'live', 'sex' => 'M']],
    ])->assertSessionHasNoErrors();

    expect(Death::where('patient_id', $mother->id)->count())->toBe(1)
        ->and(Death::where('patient_id', $mother->id)->value('source_type'))->toBe(Delivery::class);
});

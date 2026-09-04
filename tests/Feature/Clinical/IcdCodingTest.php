<?php

use App\Models\Encounter;
use App\Models\IcdCode;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\NhmisMorbidity;
use Database\Seeders\IcdCodesSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(IcdCodesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function coder(array $roleSlugs = ['physician']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to consultation and open the encounter as the physician.
 */
function consultationEncounter(User $physician): Encounter
{
    $patient = Patient::factory()->create();
    actingAs(coder(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);
}

test('the catalogue is searchable by code or description', function () {
    actingAs(coder())
        ->getJson(route('clinical.icd-search', ['q' => 'malaria']))
        ->assertOk()
        ->assertJsonFragment(['code' => 'B54'])
        ->assertJsonFragment(['code' => 'B50']);

    actingAs(coder())
        ->getJson(route('clinical.icd-search', ['q' => 'i10']))
        ->assertOk()
        ->assertJsonPath('codes.0.code', 'I10');

    actingAs(coder())
        ->getJson(route('clinical.icd-search', ['q' => 'a010']))
        ->assertJsonPath('codes.0.code', 'A01.0');

    actingAs(coder(['cashier']))
        ->getJson(route('clinical.icd-search', ['q' => 'malaria']))
        ->assertForbidden();
});

test('a diagnosis picked from the catalogue is linked to its code', function () {
    $physician = coder();
    $encounter = consultationEncounter($physician);

    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), [
            'name' => 'Unspecified malaria',
            'code' => 'b54',
            'status' => 'active',
            'role' => 'primary',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $problem = Problem::firstOrFail();

    expect($problem->code)->toBe('B54')
        ->and($problem->icd_code_id)->toBe(IcdCode::where('code', 'B54')->value('id'))
        ->and(NhmisMorbidity::groupFor($problem->code))->toBe('Malaria');
});

test('a code outside the catalogue is kept as typed but unlinked', function () {
    $physician = coder();
    $encounter = consultationEncounter($physician);

    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), [
            'name' => 'Rare condition',
            'code' => 'q99.9',
            'status' => 'active',
            'role' => 'secondary',
        ])
        ->assertSessionHasNoErrors();

    $problem = Problem::firstOrFail();

    expect($problem->code)->toBe('Q99.9')
        ->and($problem->icd_code_id)->toBeNull();
});

test('a signed consultation summarises its coded diagnoses for payers', function () {
    $physician = coder();
    $encounter = consultationEncounter($physician);

    actingAs($physician)->post(route('encounters.problems.store', $encounter), ['name' => 'Unspecified malaria', 'code' => 'B54', 'status' => 'active', 'role' => 'primary']);
    actingAs($physician)->post(route('encounters.problems.store', $encounter), ['name' => 'Anaemia, unspecified', 'code' => 'D64.9', 'status' => 'active', 'role' => 'secondary']);
    actingAs($physician)->post(route('encounters.problems.store', $encounter), ['name' => 'Typhoid?', 'code' => 'A01.0', 'status' => 'active', 'role' => 'differential']);
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Febrile illness', 'outcome' => 'home']);

    expect($encounter->fresh()->diagnosisSummary())->toBe('B54 Unspecified malaria; D64.9 Anaemia, unspecified')
        ->and($encounter->codedDiagnoses()->count())->toBe(2);
});

test('the import command loads a WHO-style file and normalises codes', function () {
    $path = tempnam(sys_get_temp_dir(), 'icd');
    file_put_contents($path, implode("\n", [
        'Code;Description',
        'Z99.9;Dependence on unspecified enabling machines and devices',
        'K359;Acute appendicitis, unspecified',
        'B54;Unspecified malaria (renamed)',
        'garbage;;',
    ]));

    $this->artisan('icd:import', ['path' => $path])
        ->expectsOutputToContain('Imported 3 codes')
        ->assertSuccessful();

    expect(IcdCode::where('code', 'Z99.9')->exists())->toBeTrue()
        ->and(IcdCode::where('code', 'K35.9')->exists())->toBeTrue()
        ->and(IcdCode::where('code', 'B54')->value('description'))->toBe('Unspecified malaria (renamed)');

    unlink($path);
});

test('the import command reads the whitespace-separated CMS release format', function () {
    $path = tempnam(sys_get_temp_dir(), 'icd');
    file_put_contents($path, implode("\n", [
        'A000    Cholera due to Vibrio cholerae 01, biovar cholerae',
        'B54     Unspecified malaria',
        'I10     Essential (primary) hypertension',
    ]));

    $this->artisan('icd:import', ['path' => $path])
        ->expectsOutputToContain('Imported 3 codes')
        ->assertSuccessful();

    expect(IcdCode::where('code', 'A00.0')->value('description'))->toBe('Cholera due to Vibrio cholerae 01, biovar cholerae')
        ->and(IcdCode::where('code', 'I10')->exists())->toBeTrue();

    unlink($path);
});

test('NHMIS morbidity groups cover the seeded catalogue', function () {
    $ungrouped = IcdCode::all()
        ->filter(fn (IcdCode $c) => NhmisMorbidity::groupFor($c->code) === null)
        ->pluck('code')
        ->all();

    // Symptom codes and general examinations deliberately fall outside the
    // disease lines; everything else in the starter list should land somewhere.
    expect($ungrouped)->each->toMatch('/^(R|Z)/');
});

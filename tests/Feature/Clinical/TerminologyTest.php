<?php

use App\Enums\ConceptClass;
use App\Enums\ObservationCode;
use App\Models\Allergy;
use App\Models\Concept;
use App\Models\ConceptSource;
use App\Models\Encounter;
use App\Models\Immunization;
use App\Models\InventoryItem;
use App\Models\LabTest;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Terminology;
use Database\Seeders\LabCompendiumSeeder;
use Database\Seeders\PharmacyStockSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Database\Seeders\TerminologySeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(LabCompendiumSeeder::class);
    $this->seed(PharmacyStockSeeder::class);
    $this->seed(TerminologySeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function termUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function termEncounter(User $actor, string $servicePoint = 'consultation'): Encounter
{
    $patient = Patient::factory()->create();

    actingAs(termUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', $servicePoint)->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return openEncounter(QueueEntry::latest('id')->firstOrFail(), $actor, $servicePoint === 'consultation' ? 'clinical.consult' : 'nursing.workspace');
}

// -------------------------------------------------------------- The registry

test('the seeder registers the coding systems and the dictionaries', function () {
    expect(ConceptSource::pluck('code')->all())->toContain('LOCAL', 'ICD10', 'LOINC', 'NEML')
        ->and(Concept::query()->ofClass(ConceptClass::Observation)->count())->toBe(count(ObservationCode::cases()))
        ->and(Concept::query()->ofClass(ConceptClass::Drug)->count())->toBeGreaterThan(80)
        ->and(Concept::query()->ofClass(ConceptClass::Allergen)->count())->toBeGreaterThan(20)
        ->and(Concept::query()->ofClass(ConceptClass::Vaccine)->count())->toBeGreaterThan(10);

    // Reseeding adds nothing twice.
    $before = Concept::count();
    $this->seed(TerminologySeeder::class);
    expect(Concept::count())->toBe($before);
});

test('observations and laboratory tests carry their LOINC codes', function () {
    $terminology = app(Terminology::class);

    $systolic = $terminology->resolve('LOINC', '8480-6');
    $haemoglobin = LabTest::where('code', 'HB')->firstOrFail();

    expect($systolic?->name)->toBe('Systolic BP')
        ->and($systolic->class)->toBe(ConceptClass::Observation)
        ->and($haemoglobin->concept_id)->not->toBeNull()
        ->and($haemoglobin->concept->codeIn('LOINC'))->toBe('718-7')
        ->and($terminology->resolve('LOINC', '718-7')?->id)->toBe($haemoglobin->concept_id);
});

test('stock items are linked to the drug they hold', function () {
    $item = InventoryItem::where('name', 'Amoxicillin')->firstOrFail();

    expect($item->concept?->class)->toBe(ConceptClass::Drug)
        ->and($item->concept->name)->toBe('Amoxicillin');
});

test('search finds concepts by name, synonym and code', function () {
    $terminology = app(Terminology::class);

    expect($terminology->search(ConceptClass::Drug, 'coartem')->first()?->name)->toBe('Artemether/Lumefantrine')
        ->and($terminology->search(ConceptClass::Drug, 'amox')->pluck('name')->all())->toContain('Amoxicillin', 'Amoxicillin/Clavulanate')
        ->and($terminology->search(ConceptClass::Drug, 'amox')->first()?->name)->toBe('Amoxicillin')
        ->and($terminology->search(ConceptClass::LabTest, '718-7')->first()?->name)->toBe('Haemoglobin')
        ->and($terminology->search(ConceptClass::Vaccine, 'penta')->first()?->name)->toBe('Pentavalent (DTP-HepB-Hib)')
        ->and($terminology->search(ConceptClass::Allergen, 'zinnat'))->toBeEmpty();
});

test('the lookup endpoint serves the pickers and validates the class', function () {
    actingAs(termUser(['physician']))
        ->getJson(route('terminology.search', ['class' => 'allergen', 'q' => 'penicillin']))
        ->assertOk()
        ->assertJsonPath('concepts.0.name', 'Penicillins');

    actingAs(termUser(['physician']))
        ->getJson(route('terminology.search', ['class' => 'spaceship', 'q' => 'x']))
        ->assertStatus(422);
});

// ----------------------------------------------------------- Coded entries

test('a prescription is coded when the name matches a drug, and stays free text otherwise', function () {
    $physician = termUser(['physician']);
    $encounter = termEncounter($physician);

    actingAs($physician)->post(route('encounters.medications.store', $encounter), ['name' => 'amoxicillin', 'dose' => '500mg', 'frequency' => 'TDS'])->assertRedirect();
    actingAs($physician)->post(route('encounters.medications.store', $encounter), ['name' => 'Coartem', 'dose' => '4 tabs', 'frequency' => 'BD'])->assertRedirect();
    actingAs($physician)->post(route('encounters.medications.store', $encounter), ['name' => 'Grandma\'s herbal tonic'])->assertRedirect();

    $byName = Medication::where('name', 'amoxicillin')->firstOrFail();
    $bySynonym = Medication::where('name', 'Coartem')->firstOrFail();
    $free = Medication::where('name', 'like', 'Grandma%')->firstOrFail();

    expect($byName->concept?->name)->toBe('Amoxicillin')
        ->and($bySynonym->concept?->name)->toBe('Artemether/Lumefantrine')
        ->and($free->concept_id)->toBeNull();
});

test('a concept chosen on the form is kept, and one of the wrong class is refused', function () {
    $physician = termUser(['physician']);
    $encounter = termEncounter($physician);
    $drug = Concept::query()->ofClass(ConceptClass::Drug)->where('name', 'Metformin')->firstOrFail();
    $vaccine = Concept::query()->ofClass(ConceptClass::Vaccine)->firstOrFail();

    actingAs($physician)
        ->post(route('encounters.medications.store', $encounter), ['name' => 'Metformin SR', 'concept_id' => $drug->id])
        ->assertSessionHasNoErrors();

    actingAs($physician)
        ->post(route('encounters.medications.store', $encounter), ['name' => 'Metformin', 'concept_id' => $vaccine->id])
        ->assertSessionHasErrors('concept_id');

    expect(Medication::where('name', 'Metformin SR')->firstOrFail()->concept_id)->toBe($drug->id);
});

test('allergies and immunizations are coded the same way', function () {
    $physician = termUser(['physician']);
    $encounter = termEncounter($physician);

    actingAs($physician)
        ->post(route('encounters.allergies.store', $encounter), ['substance' => 'Septrin', 'category' => 'drug', 'severity' => 'severe'])
        ->assertSessionHasNoErrors();

    expect(Allergy::where('substance', 'Septrin')->firstOrFail()->concept?->name)->toBe('Sulphonamides');

    $nurse = termUser(['nurse']);
    $nursing = termEncounter($nurse, 'immunization');

    actingAs($nurse)
        ->post(route('encounters.immunizations.store', $nursing), ['vaccine' => 'Penta', 'dose_label' => 'Penta 1'])
        ->assertSessionHasNoErrors();

    expect(Immunization::where('vaccine', 'Penta')->firstOrFail()->concept?->name)->toBe('Pentavalent (DTP-HepB-Hib)');
});

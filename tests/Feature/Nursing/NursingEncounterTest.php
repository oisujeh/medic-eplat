<?php

use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Enums\QueueStatus;
use App\Models\Allergy;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\Immunization;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServiceChargesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function nurseUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to a service point and return the resulting queue entry.
 */
function routeToNursingPoint(string $slug): QueueEntry
{
    $patient = Patient::factory()->create();
    actingAs(nurseUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', $slug)->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return QueueEntry::latest('id')->first();
}

/**
 * Route a patient to a nursing point and open the encounter as a nurse.
 *
 * @return array{0: Encounter, 1: User, 2: QueueEntry}
 */
function nursingEncounter(string $slug = 'anc'): array
{
    $entry = routeToNursingPoint($slug);
    $nurse = nurseUser(['nurse']);

    return [openEncounter($entry, $nurse, 'nursing.workspace'), $nurse, $entry];
}

test('a nurse can open the nursing console', function () {
    actingAs(nurseUser(['nurse']))
        ->get(route('nursing.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('nursing/Index'));
});

test('staff without the nursing module are forbidden', function () {
    actingAs(nurseUser(['cashier']))
        ->get(route('nursing.index'))
        ->assertForbidden();
});

test('opening a nursing workspace claims the patient and starts a nursing encounter', function () {
    $entry = routeToNursingPoint('anc');
    $nurse = nurseUser(['nurse']);

    actingAs($nurse)
        ->get(route('nursing.workspace', $entry))
        ->assertRedirect();

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::InService);
    expect($entry->assigned_to)->toBe($nurse->id);

    $encounter = Encounter::where('queue_entry_id', $entry->id)->firstOrFail();
    expect($encounter->type)->toBe(EncounterType::Nursing)
        ->and($encounter->status)->toBe(EncounterStatus::InProgress)
        ->and($encounter->servicePoint->slug)->toBe('anc');

    actingAs($nurse)
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('encounters/Show')
            ->where('encounter.type', 'nursing')
            ->where('encounter.service_slug', 'anc')
        );
});

test('triage opens a triage encounter', function () {
    [$encounter] = nursingEncounter('triage');

    expect($encounter->type)->toBe(EncounterType::Triage);
});

test('a nursing encounter can be saved as a draft', function () {
    [$encounter, $nurse] = nursingEncounter('family-planning');

    actingAs($nurse)->patch(route('encounters.update', $encounter), [
        'assessment' => 'Counselled on long-acting methods',
        'structured' => ['family_planning' => ['method' => 'Implant', 'counseling' => 'Side effects discussed']],
    ])->assertRedirect();

    $encounter->refresh();
    expect($encounter->assessment)->toBe('Counselled on long-acting methods');
    expect($encounter->structured['family_planning']['method'])->toBe('Implant');
    expect($encounter->status)->toBe(EncounterStatus::InProgress);
});

test('signing a nursing encounter routes the patient onward without a diagnosis or fee', function () {
    $this->seed(ServiceChargesSeeder::class);
    [$encounter, $nurse, $entry] = nursingEncounter('anc');

    $lab = ServicePoint::where('slug', 'laboratory')->firstOrFail();

    actingAs($nurse)->post(route('encounters.sign', $encounter), [
        'objective' => 'Routine antenatal review',
        'plan' => 'Given routine supplements',
        'next_service_point_id' => $lab->id,
        'next_priority' => 'normal',
    ])->assertRedirect(route('nursing.index'));

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Completed);

    $encounter->refresh();
    expect($encounter->status)->toBe(EncounterStatus::Signed)
        ->and($encounter->signed_at)->not->toBeNull()
        ->and($encounter->plan)->toBe('Given routine supplements');

    expect(
        QueueEntry::where('service_point_id', $lab->id)
            ->where('status', QueueStatus::Waiting)
            ->exists()
    )->toBeTrue();

    expect(BillCharge::count())->toBe(0);
});

test('signing without an onward point discharges the patient', function () {
    [$encounter, $nurse, $entry] = nursingEncounter('immunization');

    actingAs($nurse)->post(route('encounters.sign', $encounter), [
        'plan' => 'Vaccines administered',
    ])->assertRedirect(route('nursing.index'));

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Completed);
    expect(QueueEntry::where('visit_id', $entry->visit_id)->where('status', QueueStatus::Waiting)->exists())
        ->toBeFalse();
});

test('antenatal findings are recorded as observations on the encounter', function () {
    [$encounter, $nurse] = nursingEncounter('anc');

    actingAs($nurse)->post(route('patients.observations.store', $encounter->patient_id), [
        'encounter_id' => $encounter->id,
        'gestational_age' => 28,
        'fundal_height' => 27,
        'fetal_heart_rate' => 142,
        'presentation' => 'Cephalic',
        'systolic_bp' => 110,
        'diastolic_bp' => 70,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $set = ObservationSet::firstOrFail();
    expect($set->encounter_id)->toBe($encounter->id)
        ->and($set->visit_id)->toBe($encounter->visit_id)
        ->and($set->values()['gestational_age'])->toBe(28.0)
        ->and($set->values()['presentation'])->toBe('Cephalic');

    actingAs($nurse)->post(route('patients.observations.store', $encounter->patient_id), [
        'encounter_id' => $encounter->id,
        'gestational_age' => 99,
    ])->assertSessionHasErrors('gestational_age');
});

test('a nurse can record and remove an immunization', function () {
    [$encounter, $nurse] = nursingEncounter('immunization');

    actingAs($nurse)->post(route('encounters.immunizations.store', $encounter), [
        'vaccine' => 'Penta',
        'dose_label' => 'Penta 1',
        'batch_no' => 'B-1234',
        'route' => 'IM',
        'site' => 'Left thigh',
    ])->assertRedirect();

    $imm = Immunization::where('patient_id', $encounter->patient_id)->first();
    expect($imm->vaccine)->toBe('Penta');
    expect($imm->administered_by)->toBe($nurse->id);
    expect($imm->visit_id)->toBe($encounter->visit_id);
    expect($imm->encounter_id)->toBe($encounter->id);

    actingAs($nurse)->delete(route('encounters.immunizations.destroy', [$encounter, $imm]))
        ->assertRedirect();
    expect(Immunization::find($imm->id))->toBeNull();
});

test('recording an immunization requires a vaccine', function () {
    [$encounter, $nurse] = nursingEncounter('immunization');

    actingAs($nurse)->post(route('encounters.immunizations.store', $encounter), ['vaccine' => ''])
        ->assertSessionHasErrors('vaccine');
});

test('a nurse cannot remove an immunization belonging to another patient', function () {
    [$encounter, $nurse] = nursingEncounter('immunization');
    $otherImm = Immunization::factory()->create();

    actingAs($nurse)
        ->delete(route('encounters.immunizations.destroy', [$encounter, $otherImm]))
        ->assertNotFound();
});

test('a nurse cannot document a non-nursing queue entry', function () {
    $labEntry = routeToNursingPoint('laboratory');

    actingAs(nurseUser(['nurse']))
        ->get(route('nursing.workspace', $labEntry))
        ->assertNotFound();
});

test('the workspace surfaces the patient safety record', function () {
    [$encounter, $nurse] = nursingEncounter('anc');

    Allergy::factory()->for($encounter->patient)->create(['substance' => 'Penicillin', 'status' => 'active']);

    actingAs($nurse)
        ->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->where('allergies.0.substance', 'Penicillin')
            ->where('encounter.service_slug', 'anc')
        );
});

test('signed nursing encounters appear on the nursing console recent list and the patient chart', function () {
    [$encounter, $nurse] = nursingEncounter('anc');
    actingAs($nurse)->post(route('encounters.sign', $encounter), ['assessment' => 'Reviewed']);

    actingAs($nurse)
        ->get(route('nursing.index'))
        ->assertInertia(fn ($page) => $page
            ->has('recent', 1)
            ->where('recent.0.summary', 'Reviewed')
        );

    actingAs(nurseUser(['records-officer']))
        ->get(route('patients.show', $encounter->patient_id))
        ->assertInertia(fn ($page) => $page
            ->has('encounters', 1)
            ->where('encounters.0.type', 'nursing')
            ->where('encounters.0.service_point', 'Antenatal Care (ANC)')
        );
});

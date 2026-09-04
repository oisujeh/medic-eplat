<?php

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\BillCharge;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServiceChargesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(ServiceChargesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function admissionsUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A ward priced at the general bed rate with the given number of free beds.
 */
function wardWithBeds(int $beds = 2, string $chargeCode = 'BED-GEN'): Ward
{
    return Ward::factory()->withBeds($beds)->create([
        'bed_service_charge_id' => ServiceCharge::where('code', $chargeCode)->value('id'),
    ]);
}

/**
 * Admit a patient straight into the first free bed of a ward.
 */
function admitPatient(Ward $ward, ?Patient $patient = null, ?User $actor = null): Admission
{
    $patient ??= Patient::factory()->create();
    $actor ??= admissionsUser(['physician']);

    actingAs($actor)->post(route('admissions.store'), [
        'patient_id' => $patient->id,
        'admitting_diagnosis' => 'Severe malaria',
        'ward_id' => $ward->id,
        'bed_id' => $ward->beds()->available()->first()->id,
        'attending_id' => $actor->id,
    ]);

    return Admission::latest('id')->firstOrFail();
}

test('a nurse can open the admissions console', function () {
    wardWithBeds(3);

    actingAs(admissionsUser(['nurse']))
        ->get(route('admissions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admissions/Index')
            ->has('wards', 1)
            ->where('wards.0.total', 3)
            ->where('wards.0.available', 3)
        );
});

test('staff without the admissions module are forbidden', function () {
    actingAs(admissionsUser(['cashier']))
        ->get(route('admissions.index'))
        ->assertForbidden();
});

test('admitting a patient directly occupies the bed, opens a visit and posts the admission fee', function () {
    $ward = wardWithBeds(2);
    $patient = Patient::factory()->create();
    $physician = admissionsUser(['physician']);
    $bed = $ward->beds()->first();

    actingAs($physician)
        ->post(route('admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_diagnosis' => 'Severe malaria',
            'reason' => 'Unable to tolerate oral medication',
            'ward_id' => $ward->id,
            'bed_id' => $bed->id,
            'attending_id' => $physician->id,
        ])
        ->assertRedirect();

    $admission = Admission::firstOrFail();

    expect($admission->status)->toBe(AdmissionStatus::Admitted)
        ->and($admission->admission_number)->toStartWith('ADM/')
        ->and($admission->ward_id)->toBe($ward->id)
        ->and($admission->bed_id)->toBe($bed->id)
        ->and($admission->attending_id)->toBe($physician->id)
        ->and($admission->admitted_at)->not->toBeNull()
        ->and($admission->visit_id)->not->toBeNull()
        ->and($bed->fresh()->status)->toBe(BedStatus::Occupied)
        ->and($patient->openVisit())->not->toBeNull()
        ->and($admission->movements()->count())->toBe(1);

    $fee = BillCharge::where('source', BillCharge::SOURCE_ADMISSION)->firstOrFail();

    expect((float) $fee->total)->toBe(5000.0)
        ->and($fee->bill->visit_id)->toBe($admission->visit_id);
});

test('an admission order waits for a bed until one is assigned', function () {
    $ward = wardWithBeds(1);
    $patient = Patient::factory()->create();
    $nurse = admissionsUser(['nurse']);

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_diagnosis' => 'Pneumonia',
            'ward_id' => $ward->id,
        ])
        ->assertRedirect();

    $admission = Admission::firstOrFail();

    expect($admission->status)->toBe(AdmissionStatus::Pending)
        ->and($admission->bed_id)->toBeNull()
        ->and(BillCharge::count())->toBe(0);

    actingAs($nurse)
        ->post(route('admissions.assign', $admission), ['bed_id' => $ward->beds()->first()->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $admission->refresh();

    expect($admission->status)->toBe(AdmissionStatus::Admitted)
        ->and($admission->admitted_by)->toBe($nurse->id)
        ->and($ward->beds()->first()->status)->toBe(BedStatus::Occupied)
        ->and(BillCharge::where('source', BillCharge::SOURCE_ADMISSION)->count())->toBe(1);
});

test('a patient cannot have two active admissions', function () {
    $ward = wardWithBeds(2);
    $admission = admitPatient($ward);

    actingAs(admissionsUser(['physician']))
        ->from(route('admissions.index'))
        ->post(route('admissions.store'), [
            'patient_id' => $admission->patient_id,
            'admitting_diagnosis' => 'Again',
        ])
        ->assertRedirect(route('admissions.index', absolute: false))
        ->assertSessionHasErrors('patient_id');

    expect(Admission::count())->toBe(1);
});

test('an occupied or out-of-service bed cannot be assigned', function () {
    $ward = wardWithBeds(1);
    $taken = admitPatient($ward);
    $outOfService = Bed::factory()->outOfService()->create(['ward_id' => $ward->id, 'label' => 'Bed 9']);

    $waiting = Admission::factory()->create();

    actingAs(admissionsUser(['nurse']))
        ->post(route('admissions.assign', $waiting), ['bed_id' => $taken->bed_id])
        ->assertSessionHasErrors('bed_id');

    actingAs(admissionsUser(['nurse']))
        ->post(route('admissions.assign', $waiting), ['bed_id' => $outOfService->id])
        ->assertSessionHasErrors('bed_id');

    expect($waiting->fresh()->status)->toBe(AdmissionStatus::Pending);
});

test('a bed in a different ward from the one chosen is rejected', function () {
    $wardA = wardWithBeds(1);
    $wardB = wardWithBeds(1);

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.store'), [
            'patient_id' => Patient::factory()->create()->id,
            'admitting_diagnosis' => 'X',
            'ward_id' => $wardA->id,
            'bed_id' => $wardB->beds()->first()->id,
        ])
        ->assertSessionHasErrors('bed_id');

    expect(Admission::count())->toBe(0);
});

test('transferring moves the patient and frees the old bed', function () {
    $ward = wardWithBeds(1);
    $icu = wardWithBeds(1, 'BED-ICU');
    $admission = admitPatient($ward);
    $oldBed = $admission->bed;
    $newBed = $icu->beds()->first();

    actingAs(admissionsUser(['nurse']))
        ->post(route('admissions.transfer', $admission), ['bed_id' => $newBed->id, 'reason' => 'Deteriorating'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $admission->refresh();

    expect($admission->ward_id)->toBe($icu->id)
        ->and($admission->bed_id)->toBe($newBed->id)
        ->and($oldBed->fresh()->status)->toBe(BedStatus::Available)
        ->and($newBed->fresh()->status)->toBe(BedStatus::Occupied)
        ->and($admission->movements()->count())->toBe(2)
        ->and($admission->movements->last()->reason)->toBe('Deteriorating');
});

test('discharge frees the bed and bills each day in the ward', function () {
    Carbon::setTestNow('2026-09-01 10:00:00');
    $ward = wardWithBeds(1);
    $admission = admitPatient($ward);
    $bed = $admission->bed;

    Carbon::setTestNow('2026-09-03 09:00:00');

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.discharge', $admission), [
            'discharge_type' => 'home',
            'discharge_summary' => 'Fever settled, completed IV artesunate.',
            'follow_up_at' => '2026-09-10',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $admission->refresh();

    expect($admission->status)->toBe(AdmissionStatus::Discharged)
        ->and($admission->discharge_type->value)->toBe('home')
        ->and($admission->follow_up_at->toDateString())->toBe('2026-09-10')
        ->and($admission->lengthOfStayDays())->toBe(3)
        ->and($bed->fresh()->status)->toBe(BedStatus::Available);

    $bedCharge = BillCharge::where('source', BillCharge::SOURCE_ADMISSION)
        ->where('description', 'like', 'Bed%')
        ->firstOrFail();

    expect($bedCharge->quantity)->toBe(3)
        ->and((float) $bedCharge->unit_price)->toBe(3000.0)
        ->and((float) $bedCharge->total)->toBe(9000.0);

    Carbon::setTestNow();
});

test('a discharged patient cannot be discharged or transferred again', function () {
    $ward = wardWithBeds(2);
    $admission = admitPatient($ward);

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.discharge', $admission), ['discharge_type' => 'home']);

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.discharge', $admission), ['discharge_type' => 'home'])
        ->assertSessionHasErrors('status');

    actingAs(admissionsUser(['nurse']))
        ->post(route('admissions.transfer', $admission), ['bed_id' => $ward->beds()->available()->first()->id])
        ->assertSessionHasErrors('status');
});

test('a pending admission can be cancelled', function () {
    $admission = Admission::factory()->create();

    actingAs(admissionsUser(['physician']))
        ->post(route('admissions.cancel', $admission), ['reason' => 'Patient opted for outpatient care'])
        ->assertRedirect();

    expect($admission->fresh()->status)->toBe(AdmissionStatus::Cancelled)
        ->and($admission->fresh()->cancel_reason)->toBe('Patient opted for outpatient care');
});

test('ward notes and vitals are recorded against the admission', function () {
    $ward = wardWithBeds(1);
    $admission = admitPatient($ward);
    $nurse = admissionsUser(['nurse']);

    actingAs($nurse)
        ->post(route('admissions.notes.store', $admission), ['type' => 'nursing', 'note' => 'Comfortable overnight, afebrile.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    actingAs($nurse)
        ->post(route('patients.observations.store', $admission->patient_id), [
            'admission_id' => $admission->id,
            'temperature' => 37.2, 'systolic_bp' => 118, 'diastolic_bp' => 76, 'pulse' => 80,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $set = ObservationSet::firstOrFail();

    expect($admission->notes()->count())->toBe(1)
        ->and($admission->notes()->first()->author_id)->toBe($nurse->id)
        ->and($set->admission_id)->toBe($admission->id)
        ->and($set->visit_id)->toBe($admission->visit_id)
        ->and($set->patient_id)->toBe($admission->patient_id);

    actingAs($nurse)
        ->get(route('admissions.show', $admission))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admissions/Show')
            ->where('admission.status', 'admitted')
            ->has('notes', 1)
            ->has('observationSets', 1)
            ->has('movements', 1)
        );
});

test('ward observations need an admitted patient and the admissions module', function () {
    $ward = wardWithBeds(1);
    $admission = admitPatient($ward);

    actingAs(admissionsUser(['cashier']))
        ->post(route('patients.observations.store', $admission->patient_id), ['admission_id' => $admission->id, 'pulse' => 80])
        ->assertForbidden();

    $admission->update(['status' => AdmissionStatus::Discharged, 'discharged_at' => now()]);

    actingAs(admissionsUser(['nurse']))
        ->post(route('patients.observations.store', $admission->patient_id), ['admission_id' => $admission->id, 'pulse' => 80])
        ->assertForbidden();
});

test('completing a consultation with an admit outcome orders an admission', function () {
    $patient = Patient::factory()->create();
    $physician = admissionsUser(['physician']);

    actingAs(admissionsUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);
    $entry = QueueEntry::latest('id')->firstOrFail();
    $encounter = openEncounter($entry, $physician);

    actingAs($physician)
        ->post(route('encounters.sign', $encounter), [
            'assessment' => 'Diabetic ketoacidosis',
            'outcome' => 'admit',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $admission = Admission::firstOrFail();

    expect($admission->status)->toBe(AdmissionStatus::Pending)
        ->and($admission->patient_id)->toBe($patient->id)
        ->and($admission->visit_id)->toBe($entry->visit_id)
        ->and($admission->encounter_id)->not->toBeNull()
        ->and($admission->admitting_diagnosis)->toBe('Diabetic ketoacidosis')
        ->and($admission->attending_id)->toBe($physician->id);
});

test('the patient profile shows the active admission', function () {
    $ward = wardWithBeds(1);
    $admission = admitPatient($ward);

    actingAs(admissionsUser(['physician']))
        ->get(route('patients.show', $admission->patient_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeAdmission.id', $admission->id)
            ->where('activeAdmission.ward', $ward->name)
            ->where('canAdmit', true)
        );
});

test('the inpatient reports run', function () {
    $ward = wardWithBeds(2);
    admitPatient($ward);
    $cmd = admissionsUser(['chief-medical-director']);

    actingAs($cmd)->get(route('reports.run', 'admissions-log'))->assertOk();
    actingAs($cmd)->get(route('reports.run', 'bed-occupancy'))->assertOk();
});

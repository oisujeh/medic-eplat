<?php

use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Enums\QueueStatus;
use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\BillCharge;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\Problem;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\LabCompendiumSeeder;
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
function clinUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to a service point and return the resulting queue entry.
 */
function routeTo(string $slug): QueueEntry
{
    $patient = Patient::factory()->create();
    actingAs(clinUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', $slug)->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return QueueEntry::latest('id')->first();
}

/**
 * Route a patient to consultation and open the encounter as a physician.
 *
 * @return array{0: Encounter, 1: User, 2: QueueEntry}
 */
function consultation(): array
{
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);

    return [openEncounter($entry, $physician), $physician, $entry];
}

test('a physician can open the clinical console', function () {
    actingAs(clinUser(['physician']))
        ->get(route('clinical.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clinical/Index'));
});

test('staff without the clinical module are forbidden', function () {
    actingAs(clinUser(['nurse']))
        ->get(route('clinical.index'))
        ->assertForbidden();
});

test('opening a consultation claims the patient and starts an encounter', function () {
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);

    actingAs($physician)
        ->get(route('clinical.consult', $entry))
        ->assertRedirect();

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::InService);
    expect($entry->assigned_to)->toBe($physician->id);

    $encounter = Encounter::where('queue_entry_id', $entry->id)->firstOrFail();
    expect($encounter->type)->toBe(EncounterType::Consultation)
        ->and($encounter->status)->toBe(EncounterStatus::InProgress)
        ->and($encounter->author_id)->toBe($physician->id)
        ->and($encounter->service_point_id)->toBe($entry->service_point_id);

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('encounters/Show')
            ->where('encounter.type', 'consultation')
            ->where('can.sign', true)
        );
});

test('reopening a consultation resumes the same encounter', function () {
    [$encounter, $physician, $entry] = consultation();

    actingAs($physician)->get(route('clinical.consult', $entry))->assertRedirect(route('encounters.show', $encounter));

    expect(Encounter::where('queue_entry_id', $entry->id)->count())->toBe(1);
});

test('a consultation can be saved as a draft', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->patch(route('encounters.update', $encounter), [
        'presenting_complaint' => 'Headache for 3 days',
        'assessment' => 'Tension headache',
    ])->assertRedirect();

    $encounter->refresh();
    expect($encounter->presenting_complaint)->toBe('Headache for 3 days');
    expect($encounter->assessment)->toBe('Tension headache');
    expect($encounter->status)->toBe(EncounterStatus::InProgress);
});

test('signing a consultation requires a diagnosis', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => ''])
        ->assertSessionHasErrors('assessment');
});

test('a coded diagnosis satisfies the diagnosis requirement', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.problems.store', $encounter), [
        'name' => 'Malaria', 'code' => 'B54', 'status' => 'active', 'role' => 'primary',
    ]);

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('clinical.index'));

    expect($encounter->fresh()->isSigned())->toBeTrue();
});

test('signing records the signer as author, signs off the encounter and routes the patient onward', function () {
    $entry = routeTo('consultation');
    $opener = clinUser(['physician']);
    $signer = clinUser(['physician']);
    $encounter = openEncounter($entry, $opener);

    // Hand the patient over to a colleague, who signs.
    $entry->update(['assigned_to' => $signer->id]);

    $pharmacy = ServicePoint::where('slug', 'pharmacy')->firstOrFail();

    actingAs($signer)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Malaria',
        'plan' => 'Antimalarials, review in 3 days',
        'next_service_point_id' => $pharmacy->id,
        'next_priority' => 'normal',
    ])->assertRedirect(route('clinical.index'));

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Completed);

    $encounter->refresh();
    expect($encounter->status)->toBe(EncounterStatus::Signed)
        ->and($encounter->signed_at)->not->toBeNull()
        ->and($encounter->author_id)->toBe($signer->id)
        ->and($encounter->assessment)->toBe('Malaria');

    expect(
        QueueEntry::where('service_point_id', $pharmacy->id)
            ->where('status', QueueStatus::Waiting)
            ->exists()
    )->toBeTrue();
});

test('a signed encounter is locked against every kind of edit', function () {
    [$encounter, $physician] = consultation();
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria']);

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Changed'])
        ->assertForbidden();
    actingAs($physician)->patch(route('encounters.update', $encounter), ['assessment' => 'Changed'])
        ->assertForbidden();
    actingAs($physician)->post(route('encounters.problems.store', $encounter), ['name' => 'Late', 'status' => 'active'])
        ->assertForbidden();
    actingAs($physician)->post(route('encounters.medications.store', $encounter), ['name' => 'Late drug'])
        ->assertForbidden();
    actingAs($physician)->post(route('patients.observations.store', $encounter->patient_id), [
        'encounter_id' => $encounter->id, 'pulse' => 80,
    ])->assertForbidden();

    expect($encounter->fresh()->assessment)->toBe('Malaria')
        ->and(Problem::where('name', 'Late')->exists())->toBeFalse();
});

test('a signed encounter can be read by colleagues in the module and extended with an addendum', function () {
    [$encounter, $physician] = consultation();
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria']);

    $colleague = clinUser(['physician']);

    // Open: locked to the assignee. Signed: readable across the module.
    actingAs($colleague)->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.document', false)
            ->where('can.sign', false)
            ->where('can.addend', true)
        );

    actingAs($colleague)->post(route('encounters.addenda.store', $encounter), ['body' => ''])
        ->assertSessionHasErrors('body');

    actingAs($colleague)->post(route('encounters.addenda.store', $encounter), [
        'body' => 'RDT returned positive after sign-off; ACT started.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $addendum = $encounter->addenda()->firstOrFail();
    expect($addendum->author_id)->toBe($colleague->id)
        ->and($addendum->recorded_at)->not->toBeNull()
        ->and($encounter->fresh()->assessment)->toBe('Malaria');

    actingAs($physician)->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->has('addenda', 1)
            ->where('addenda.0.author', $colleague->name)
            ->where('addenda.0.body', 'RDT returned positive after sign-off; ACT started.')
        );

    actingAs(clinUser(['records-officer']))
        ->get(route('patients.show', $encounter->patient_id))
        ->assertInertia(fn ($page) => $page->has('encounters.0.addenda', 1));
});

test('an addendum cannot be added to an open encounter or by staff outside the module', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.addenda.store', $encounter), ['body' => 'Too early'])
        ->assertForbidden();

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria']);

    actingAs(clinUser(['nurse']))->post(route('encounters.addenda.store', $encounter), ['body' => 'Wrong module'])
        ->assertForbidden();

    expect($encounter->addenda()->count())->toBe(0);
});

test('signing is atomic: a failure after sign-off leaves nothing behind', function () {
    $this->seed(ServiceChargesSeeder::class);
    [$encounter, $physician, $entry] = consultation();

    $this->mock(BillingService::class, function ($mock) {
        $mock->shouldReceive('openBillFor')->andThrow(new RuntimeException('billing down'));
    });

    try {
        actingAs($physician)->withoutExceptionHandling()
            ->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria']);
        $this->fail('The billing failure should have surfaced.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('billing down');
    }

    expect($encounter->fresh()->status)->toBe(EncounterStatus::InProgress)
        ->and($entry->fresh()->status)->toBe(QueueStatus::InService)
        ->and(BillCharge::count())->toBe(0);
});

test('a consultation persists structured stage data, outcome and follow-up', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), [
        'assessment' => 'Essential hypertension',
        'outcome' => 'home',
        'follow_up_at' => '2026-08-01T09:00',
        'structured' => [
            'subjective' => ['past_medical_history' => 'Asthma'],
            'examination' => [
                'general' => ['appearance' => 'well', 'pallor' => true],
                'systems' => ['respiratory' => 'Clear on auscultation'],
            ],
            'plan' => ['procedures' => ['ECG'], 'counseling' => ['diet']],
            'follow_up' => [
                'interval' => '1m',
                'monitoring_goals' => ['repeat_bp'],
                'patient_instructions' => 'Reduce salt intake',
            ],
        ],
    ])->assertRedirect(route('clinical.index'));

    $encounter->refresh();
    expect($encounter->outcome?->value)->toBe('home');
    expect($encounter->follow_up_at)->not->toBeNull();
    expect($encounter->structured['subjective']['past_medical_history'])->toBe('Asthma');
    expect($encounter->structured['examination']['general']['appearance'])->toBe('well');
    expect($encounter->structured['examination']['general']['pallor'])->toBeTrue();
    expect($encounter->structured['examination']['systems']['respiratory'])->toBe('Clear on auscultation');
    expect($encounter->structured['plan']['procedures'])->toBe(['ECG']);
    expect($encounter->structured['follow_up']['interval'])->toBe('1m');
});

test('signing rejects an invalid outcome', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)
        ->post(route('encounters.sign', $encounter), ['assessment' => 'X', 'outcome' => 'teleported'])
        ->assertSessionHasErrors('outcome');
});

test('a clinician can book a follow-up appointment from the consultation', function () {
    [$encounter, $physician] = consultation();
    $clinic = ServicePoint::where('slug', 'consultation')->firstOrFail();

    actingAs($physician)->post(route('encounters.follow-up', $encounter), [
        'service_point_id' => $clinic->id,
        'scheduled_start' => now()->addWeek()->toDateTimeString(),
    ])->assertRedirect();

    $appt = Appointment::where('patient_id', $encounter->patient_id)->first();
    expect($appt)->not->toBeNull();
    expect($appt->source->value)->toBe('follow_up');
    expect($appt->status->value)->toBe('scheduled');
    expect($appt->encounter_id)->toBe($encounter->id);
    expect($appt->service_point_id)->toBe($clinic->id);
});

test('a clinician can add a diagnosis tagged with an assessment role', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.problems.store', $encounter), [
        'name' => 'Essential hypertension',
        'code' => 'I10',
        'status' => 'active',
        'role' => 'primary',
    ])->assertRedirect();

    expect(Problem::where('patient_id', $encounter->patient_id)->first()->role)->toBe('primary');
});

test('adding a diagnosis rejects an invalid role', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), [
            'name' => 'Hypertension', 'status' => 'active', 'role' => 'tertiary',
        ])
        ->assertSessionHasErrors('role');
});

test('signed encounters appear on the patient chart timeline', function () {
    [$encounter, $physician] = consultation();
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Hypertension']);

    actingAs(clinUser(['records-officer']))
        ->get(route('patients.show', $encounter->patient_id))
        ->assertInertia(fn ($page) => $page
            ->has('encounters', 1)
            ->where('encounters.0.type', 'consultation')
            ->where('encounters.0.assessment', 'Hypertension')
            ->where('encounters.0.status', 'signed')
        );
});

test('the consultation surfaces the patient clinical record', function () {
    [$encounter, $physician] = consultation();
    $patient = $encounter->patient;

    Allergy::factory()->for($patient)->create(['substance' => 'Penicillin', 'severity' => 'severe', 'status' => 'active']);
    Problem::factory()->for($patient)->create(['name' => 'Hypertension', 'status' => 'active']);
    Medication::factory()->for($patient)->create(['name' => 'Amlodipine', 'dose' => '5mg', 'frequency' => 'OD', 'status' => 'active']);
    LabResult::factory()->for($patient)->create(['name' => 'Fasting Blood Sugar', 'value' => '5.4', 'unit' => 'mmol/L', 'status' => 'resulted']);
    PatientAlert::factory()->for($patient)->create(['message' => 'Missed appointment', 'is_active' => true]);

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->where('allergies.0.substance', 'Penicillin')
            ->where('problems.0.name', 'Hypertension')
            ->where('medications.0.label', 'Amlodipine 5mg OD')
            ->where('labResults.0.name', 'Fasting Blood Sugar')
            ->where('labResults.0.display_value', '5.4 mmol/L')
            ->where('alerts.0.message', 'Missed appointment')
        );
});

test('the consultation only surfaces active clinical records', function () {
    [$encounter, $physician] = consultation();
    $patient = $encounter->patient;

    Allergy::factory()->for($patient)->create(['status' => 'inactive']);
    Problem::factory()->for($patient)->create(['status' => 'resolved']);
    Medication::factory()->for($patient)->create(['status' => 'stopped']);

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->has('allergies', 0)
            ->has('problems', 0)
            ->has('medications', 0)
        );
});

test('a clinician can add a problem to the problem list', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.problems.store', $encounter), [
        'name' => 'Hypertension',
        'code' => 'I10',
        'status' => 'active',
    ])->assertRedirect();

    $problem = Problem::where('patient_id', $encounter->patient_id)->first();
    expect($problem->name)->toBe('Hypertension');
    expect($problem->status)->toBe('active');
    expect($problem->encounter_id)->toBe($encounter->id);
    expect($problem->recorded_by)->toBe($physician->id);
});

test('adding a problem requires a name', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), ['name' => '', 'status' => 'active'])
        ->assertSessionHasErrors('name');
});

test('a clinician can resolve a problem', function () {
    [$encounter, $physician] = consultation();
    $problem = Problem::factory()->for($encounter->patient)->create(['status' => 'active']);

    actingAs($physician)
        ->post(route('encounters.problems.resolve', [$encounter, $problem]))
        ->assertRedirect();

    expect($problem->fresh()->status)->toBe('resolved');
    expect($problem->fresh()->resolved_date)->not->toBeNull();
});

test('a clinician can prescribe a medication', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.medications.store', $encounter), [
        'name' => 'Amlodipine',
        'dose' => '5mg',
        'frequency' => 'OD',
        'route' => 'PO',
    ])->assertRedirect();

    $med = Medication::where('patient_id', $encounter->patient_id)->first();
    expect($med->name)->toBe('Amlodipine');
    expect($med->status)->toBe('active');
    expect($med->prescribed_by)->toBe($physician->id);
    expect($med->visit_id)->toBe($encounter->visit_id);
    expect($med->encounter_id)->toBe($encounter->id);
});

test('a clinician can stop a medication', function () {
    [$encounter, $physician] = consultation();
    $med = Medication::factory()->for($encounter->patient)->create(['status' => 'active']);

    actingAs($physician)
        ->post(route('encounters.medications.stop', [$encounter, $med]))
        ->assertRedirect();

    expect($med->fresh()->status)->toBe('stopped');
    expect($med->fresh()->stopped_at)->not->toBeNull();
});

test('a clinician cannot stop a medication belonging to another patient', function () {
    [$encounter, $physician] = consultation();
    $otherMed = Medication::factory()->create();

    actingAs($physician)
        ->post(route('encounters.medications.stop', [$encounter, $otherMed]))
        ->assertNotFound();
});

test('a clinician can edit and delete a problem', function () {
    [$encounter, $physician] = consultation();
    $problem = Problem::factory()->for($encounter->patient)->create(['name' => 'HTN', 'status' => 'active']);

    actingAs($physician)->patch(route('encounters.problems.update', [$encounter, $problem]), [
        'name' => 'Hypertension',
        'code' => 'I10',
        'status' => 'chronic',
    ])->assertRedirect();

    expect($problem->fresh()->name)->toBe('Hypertension');
    expect($problem->fresh()->status)->toBe('chronic');

    actingAs($physician)->delete(route('encounters.problems.destroy', [$encounter, $problem]))->assertRedirect();
    expect(Problem::find($problem->id))->toBeNull();
});

test('a clinician can edit and delete a medication', function () {
    [$encounter, $physician] = consultation();
    $med = Medication::factory()->for($encounter->patient)->create(['name' => 'Amlo', 'dose' => '5mg']);

    actingAs($physician)->patch(route('encounters.medications.update', [$encounter, $med]), [
        'name' => 'Amlodipine',
        'dose' => '10mg',
        'frequency' => 'OD',
    ])->assertRedirect();

    expect($med->fresh()->name)->toBe('Amlodipine');
    expect($med->fresh()->dose)->toBe('10mg');

    actingAs($physician)->delete(route('encounters.medications.destroy', [$encounter, $med]))->assertRedirect();
    expect(Medication::find($med->id))->toBeNull();
});

test('a clinician can record and remove an allergy', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.allergies.store', $encounter), [
        'substance' => 'Penicillin',
        'category' => 'drug',
        'severity' => 'severe',
        'reaction' => 'Anaphylaxis',
    ])->assertRedirect();

    $allergy = Allergy::where('patient_id', $encounter->patient_id)->first();
    expect($allergy->substance)->toBe('Penicillin');
    expect($allergy->severity)->toBe('severe');
    expect($allergy->recorded_by)->toBe($physician->id);

    actingAs($physician)->delete(route('encounters.allergies.destroy', [$encounter, $allergy]))->assertRedirect();
    expect(Allergy::find($allergy->id))->toBeNull();
});

test('recording an allergy rejects an invalid severity', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)
        ->post(route('encounters.allergies.store', $encounter), ['substance' => 'Latex', 'severity' => 'lethal'])
        ->assertSessionHasErrors('severity');
});

test('a clinician can order a lab test and record its result', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.lab-orders.store', $encounter), [
        'name' => 'Full Blood Count',
        'specimen' => 'Blood',
    ])->assertRedirect();

    $lab = LabResult::where('patient_id', $encounter->patient_id)->first();
    expect($lab->status)->toBe('pending');
    expect($lab->ordered_by)->toBe($physician->id);
    expect($lab->visit_id)->toBe($encounter->visit_id);

    actingAs($physician)->patch(route('encounters.lab-results.update', [$encounter, $lab]), [
        'value' => '13.8',
        'unit' => 'g/dL',
        'reference_range' => '13-17',
        'flag' => 'normal',
    ])->assertRedirect();

    $lab->refresh();
    expect($lab->status)->toBe('resulted');
    expect($lab->value)->toBe('13.8');
    expect($lab->resulted_by)->toBe($physician->id);
    expect($lab->resulted_at)->not->toBeNull();
});

test('a clinician can order catalogue panels which expand into a requisition', function () {
    $this->seed(LabCompendiumSeeder::class);
    [$encounter, $physician] = consultation();
    $fbc = LabTest::where('code', 'FBC')->firstOrFail();

    actingAs($physician)->post(route('encounters.lab-orders.store', $encounter), [
        'lab_test_ids' => [$fbc->id],
        'priority' => 'urgent',
        'clinical_details' => 'Anaemia workup',
    ])->assertRedirect();

    $order = LabOrder::where('patient_id', $encounter->patient_id)->first();
    expect($order)->not->toBeNull();
    expect($order->priority->value)->toBe('urgent');
    expect($order->clinical_details)->toBe('Anaemia workup');
    expect($order->results()->count())->toBe(6);       // FBC's six analytes
    expect($order->encounter_id)->toBe($encounter->id);
    expect($order->ordered_by)->toBe($physician->id);
});

test('ordering requires at least one test', function () {
    [$encounter, $physician] = consultation();

    actingAs($physician)
        ->post(route('encounters.lab-orders.store', $encounter), ['priority' => 'normal'])
        ->assertSessionHasErrors('name');
});

test('a clinician can cancel a pending lab order', function () {
    [$encounter, $physician] = consultation();
    $lab = LabResult::factory()->for($encounter->patient)->create(['status' => 'pending']);

    actingAs($physician)
        ->delete(route('encounters.lab-results.destroy', [$encounter, $lab]))
        ->assertRedirect();

    expect(LabResult::find($lab->id))->toBeNull();
});

test('a clinician cannot mutate a record belonging to another patient', function () {
    [$encounter, $physician] = consultation();
    $otherProblem = Problem::factory()->create();

    actingAs($physician)
        ->delete(route('encounters.problems.destroy', [$encounter, $otherProblem]))
        ->assertNotFound();
});

test('a clinician cannot document a non-clinical queue entry', function () {
    $labEntry = routeTo('laboratory');

    actingAs(clinUser(['physician']))
        ->get(route('clinical.consult', $labEntry))
        ->assertNotFound();
});

test('staff outside the encounter module cannot open or document it', function () {
    [$encounter] = consultation();

    actingAs(clinUser(['nurse']))
        ->get(route('encounters.show', $encounter))
        ->assertForbidden();

    actingAs(clinUser(['nurse']))
        ->post(route('encounters.problems.store', $encounter), ['name' => 'X', 'status' => 'active'])
        ->assertForbidden();
});

test('a consultation assigned to another clinician is closed to colleagues', function () {
    [$encounter] = consultation();

    actingAs(clinUser(['physician']))
        ->get(route('encounters.show', $encounter))
        ->assertForbidden();
});

test('signing a consultation posts the consultation fee once', function () {
    $this->seed(ServiceChargesSeeder::class);
    ServiceCharge::where('code', ServiceCharge::CONSULTATION)->update(['price' => 1500]);
    [$encounter, $physician] = consultation();

    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria'])->assertRedirect();

    expect(BillCharge::where('source', BillCharge::SOURCE_CONSULTATION)->count())->toBe(1)
        ->and((float) BillCharge::first()->total)->toBe(1500.0);
});

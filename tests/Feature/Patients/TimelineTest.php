<?php

use App\Enums\DischargeType;
use App\Enums\TimelineKind;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\Immunization;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Medication;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Services\AdmissionService;
use App\Services\PatientTimeline;
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
function timelineUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A patient with a little of everything on record, spread over several days.
 */
function historiedPatient(): Patient
{
    $patient = Patient::factory()->create(['created_at' => now()->subDays(30)]);
    $visit = Visit::factory()->for($patient)->create(['opened_at' => now()->subDays(10)]);

    $encounter = Encounter::factory()->create([
        'patient_id' => $patient->id, 'visit_id' => $visit->id,
        'started_at' => now()->subDays(10), 'signed_at' => now()->subDays(10)->addHour(), 'status' => 'signed',
        'presenting_complaint' => 'Fever and headache for three days',
    ]);
    Problem::factory()->create(['patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'name' => 'Malaria', 'code' => 'B54', 'role' => 'primary', 'created_at' => now()->subDays(10)->addMinutes(30)]);
    Medication::factory()->create(['patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'name' => 'Artemether-lumefantrine', 'dose' => '80/480mg', 'frequency' => 'BD', 'created_at' => now()->subDays(10)->addMinutes(40)]);
    ObservationSet::factory()->create(['patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'recorded_at' => now()->subDays(10)->addMinutes(5)]);

    $order = LabOrder::factory()->create(['patient_id' => $patient->id, 'encounter_id' => $encounter->id, 'created_at' => now()->subDays(10)->addMinutes(45), 'verified_at' => now()->subDays(9), 'status' => 'completed']);
    LabResult::factory()->create(['patient_id' => $patient->id, 'lab_order_id' => $order->id, 'name' => 'Malaria parasite', 'value' => 'Positive', 'unit' => null, 'flag' => 'abnormal', 'status' => 'resulted']);

    Immunization::factory()->create(['patient_id' => $patient->id, 'vaccine' => 'Tetanus toxoid', 'administered_at' => now()->subDays(20)]);

    return $patient;
}

test('the timeline lists every kind of record, newest first', function () {
    $patient = historiedPatient();

    $ward = Ward::factory()->create();
    $bed = Bed::factory()->create(['ward_id' => $ward->id]);
    $physician = timelineUser(['physician']);
    $admissions = app(AdmissionService::class);
    $admission = Admission::factory()->create(['patient_id' => $patient->id, 'ward_id' => $ward->id, 'bed_id' => $bed->id, 'status' => 'admitted', 'admitted_at' => now()->subDays(5), 'admitting_diagnosis' => 'Severe malaria']);
    $bed->update(['status' => 'occupied']);
    actingAs($physician);
    $admissions->discharge($admission, $physician, DischargeType::Home, 'Recovered.');

    $entries = app(PatientTimeline::class)->entries($patient);
    $kinds = $entries->map(fn ($e) => $e->kind)->unique()->values()->all();

    expect($kinds)->toContain(
        TimelineKind::Registration, TimelineKind::Encounter, TimelineKind::Diagnosis, TimelineKind::Observation,
        TimelineKind::LabOrder, TimelineKind::LabResult, TimelineKind::Medication, TimelineKind::Immunization,
        TimelineKind::Admission, TimelineKind::Discharge,
    );

    // Newest first: the discharge today, the registration a month ago last.
    expect($entries->first()->kind)->toBe(TimelineKind::Discharge)
        ->and($entries->last()->kind)->toBe(TimelineKind::Registration);

    $timestamps = $entries->map(fn ($e) => $e->at->getTimestamp())->all();
    $sorted = $timestamps;
    rsort($sorted);
    expect($timestamps)->toBe($sorted);
});

test('entries carry what a clinician needs at a glance', function () {
    $patient = historiedPatient();
    $entries = app(PatientTimeline::class)->entries($patient)->keyBy('key');

    $diagnosis = $entries->first(fn ($e) => $e->kind === TimelineKind::Diagnosis);
    $result = $entries->first(fn ($e) => $e->kind === TimelineKind::LabResult);
    $medication = $entries->first(fn ($e) => $e->kind === TimelineKind::Medication);

    expect($diagnosis->title)->toBe('B54 Malaria')
        ->and($diagnosis->detail)->toContain('Primary')
        ->and($result->detail)->toContain('Malaria parasite Positive (abnormal)')
        ->and($result->badge)->toBe('1 abnormal')
        ->and($result->tone)->toBe('red')
        ->and($medication->title)->toBe('Artemether-lumefantrine 80/480mg BD')
        ->and($medication->href)->not->toBeNull();
});

test('the presented timeline can be filtered by kind and counts every kind', function () {
    $patient = historiedPatient();
    $timeline = app(PatientTimeline::class);

    $all = $timeline->present($patient);
    $labsOnly = $timeline->present($patient, ['lab_order', 'lab_result']);

    expect(collect($all['kinds'])->pluck('value')->all())->toContain('encounter', 'diagnosis', 'lab_result', 'immunization')
        ->and(collect($all['kinds'])->firstWhere('value', 'diagnosis')['count'])->toBe(1)
        ->and(collect($labsOnly['entries'])->pluck('kind')->unique()->values()->all())->toBe(['lab_result', 'lab_order'])
        ->and($all['entries'][0])->toHaveKeys(['key', 'kind', 'icon', 'date_label', 'time', 'title', 'detail', 'badge', 'tone', 'href', 'by']);
});

test('the chart and the encounter screen carry the timeline', function () {
    $patient = historiedPatient();
    $physician = timelineUser(['physician']);

    actingAs($physician)
        ->get(route('patients.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('timeline.entries', fn ($entries) => $entries->etc())
            ->where('timeline.entries', fn ($entries) => count($entries) >= 8)
            ->has('timeline.kinds'));

    $encounter = $patient->encounters()->firstOrFail();

    actingAs(timelineUser(['super-administrator']))
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('timeline', fn ($t) => $t->etc()));
});

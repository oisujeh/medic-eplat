<?php

use App\Enums\AlertLevel;
use App\Enums\ObservationCode;
use App\Models\Observation;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\PatientFlowService;
use App\Support\ObservationInterpreter;
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
function obsUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to a service point and return the (in-service) queue entry.
 */
function inServiceEntry(string $servicePointSlug, User $nurse, ?Patient $patient = null): QueueEntry
{
    $patient ??= Patient::factory()->create();
    $sp = ServicePoint::where('slug', $servicePointSlug)->firstOrFail();

    actingAs(obsUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => $sp->id,
        'priority' => 'normal',
    ]);

    $entry = QueueEntry::latest('id')->firstOrFail();
    app(PatientFlowService::class)->call($entry, $nurse);

    return $entry->refresh();
}

function recordFromQueue(User $nurse, QueueEntry $entry, array $readings)
{
    return actingAs($nurse)->post(route('patients.observations.store', $entry->patient_id), [
        'queue_entry_id' => $entry->id,
        ...$readings,
    ]);
}

test('a nurse can record vitals from the queue and BMI is derived', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, [
        'temperature' => 37.2,
        'systolic_bp' => 120,
        'diastolic_bp' => 80,
        'pulse' => 72,
        'spo2' => 98,
        'weight' => 80,
        'height' => 178,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $set = ObservationSet::first();
    expect($set)->not->toBeNull();
    expect($set->visit_id)->toBe($entry->visit_id);
    expect($set->queue_entry_id)->toBe($entry->id);
    expect($set->recorded_by)->toBe($nurse->id);
    expect($set->observations()->count())->toBe(8); // seven entered + BMI

    // 80 / (1.78^2) = 25.2
    expect($set->get(ObservationCode::Bmi)->value)->toBe(25.2);
    expect($set->bloodPressure())->toBe('120/80');
    expect($set->alert_level)->toBe(AlertLevel::Normal);
});

test('recording requires at least one measurement', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, ['notes' => 'nothing measured'])
        ->assertSessionHasErrors('temperature');

    expect(ObservationSet::count())->toBe(0);
});

test('out-of-range values are rejected', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, ['temperature' => 80, 'spo2' => 250])
        ->assertSessionHasErrors(['temperature', 'spo2']);
});

test('staff of another service point cannot record vitals against a queue entry', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    // A pharmacist has neither the nursing module nor triage access.
    recordFromQueue(obsUser(['pharmacy-staff']), $entry, ['pulse' => 70])
        ->assertForbidden();
});

test('a queue entry for another patient cannot carry the readings', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);
    $other = Patient::factory()->create();

    actingAs($nurse)->post(route('patients.observations.store', $other), [
        'queue_entry_id' => $entry->id,
        'pulse' => 70,
    ])->assertForbidden();
});

test('recorded observations appear on the patient chart', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, ['temperature' => 36.8, 'pulse' => 68]);

    actingAs(obsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('observationSets', 1)
            ->where('observationSets.0.values.pulse', 68)
            ->where('observationSets.0.readings.1.display', '68 bpm')
        );
});

test('the queue management page surfaces the latest readings', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);
    recordFromQueue($nurse, $entry, ['pulse' => 70]);

    actingAs($nurse)
        ->get(route('queues.show', 'triage'))
        ->assertInertia(fn ($page) => $page
            ->where('entries.0.latest_observations.values.pulse', 70)
        );
});

test('abnormal readings are flagged with a severity level', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    // Fever (warning) + low SpO2 (critical) -> overall critical.
    recordFromQueue($nurse, $entry, ['temperature' => 38.6, 'spo2' => 88]);

    actingAs(obsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertInertia(fn ($page) => $page
            ->where('observationSets.0.alert_level', 'critical')
            ->where('observationSets.0.flags', fn ($flags) => collect($flags)->pluck('label')->contains('Fever')
                && collect($flags)->pluck('label')->contains('Low SpO₂')
            )
        );
});

test('normal readings carry no flags', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, [
        'temperature' => 36.8,
        'pulse' => 72,
        'spo2' => 98,
        'systolic_bp' => 118,
        'diastolic_bp' => 76,
    ]);

    actingAs(obsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertInertia(fn ($page) => $page
            ->where('observationSets.0.alert_level', 'normal')
            ->where('observationSets.0.flags', [])
        );
});

test('pulse and respiratory rate are judged against the child\'s age', function () {
    $nurse = obsUser(['nurse']);
    $infant = Patient::factory()->create(['date_of_birth' => now()->subMonths(6)->toDateString()]);
    $entry = inServiceEntry('triage', $nurse, $infant);

    // 130 bpm is tachycardia in an adult but normal for an infant.
    recordFromQueue($nurse, $entry, ['pulse' => 130, 'respiratory_rate' => 40]);

    expect(ObservationSet::first()->alert_level)->toBe(AlertLevel::Normal);
    expect(ObservationInterpreter::interpret(ObservationCode::Pulse, 130)['level'])->toBe(AlertLevel::Critical);
});

test('readings are queryable per code for trends', function () {
    $nurse = obsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    recordFromQueue($nurse, $entry, ['weight' => 80]);
    recordFromQueue($nurse, $entry, ['weight' => 79.5]);

    $weights = Observation::where('patient_id', $entry->patient_id)
        ->where('code', ObservationCode::Weight)
        ->orderBy('recorded_at')
        ->pluck('value');

    expect($weights->all())->toBe([80.0, 79.5]);
});

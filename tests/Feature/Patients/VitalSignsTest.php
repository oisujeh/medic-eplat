<?php

use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Models\VitalSign;
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
function vitalsUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to a service point and return the (in-service) queue entry.
 */
function inServiceEntry(string $servicePointSlug, User $nurse): QueueEntry
{
    $patient = Patient::factory()->create();
    $sp = ServicePoint::where('slug', $servicePointSlug)->firstOrFail();

    actingAs(vitalsUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => $sp->id,
        'priority' => 'normal',
    ]);

    $entry = QueueEntry::first();
    actingAs($nurse)->post(route('queue-entries.call', $entry));

    return $entry->refresh();
}

test('a nurse can record vitals and BMI is calculated', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'temperature_c' => 37.2,
        'systolic_bp' => 120,
        'diastolic_bp' => 80,
        'pulse_bpm' => 72,
        'spo2' => 98,
        'weight_kg' => 80,
        'height_cm' => 178,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $vitals = VitalSign::first();
    expect($vitals)->not->toBeNull();
    expect($vitals->visit_id)->toBe($entry->visit_id);
    expect($vitals->queue_entry_id)->toBe($entry->id);
    expect($vitals->recorded_by)->toBe($nurse->id);
    // 80 / (1.78^2) = 25.2
    expect($vitals->bmi)->toBe(25.2);
});

test('recording requires at least one measurement', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'notes' => 'nothing measured',
    ])->assertSessionHasErrors('temperature_c');

    expect(VitalSign::count())->toBe(0);
});

test('out-of-range values are rejected', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'temperature_c' => 80,
        'spo2' => 250,
    ])->assertSessionHasErrors(['temperature_c', 'spo2']);
});

test('staff of another service point cannot record vitals', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    // A pharmacist has neither the nursing module nor triage access.
    actingAs(vitalsUser(['pharmacy-staff']))
        ->post(route('queue-entries.vitals', $entry), ['pulse_bpm' => 70])
        ->assertForbidden();
});

test('recorded vitals appear on the patient profile', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'temperature_c' => 36.8,
        'pulse_bpm' => 68,
    ]);

    actingAs(vitalsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('vitals', 1)
            ->where('vitals.0.pulse_bpm', 68)
        );
});

test('triage worklist flags that it captures vitals and surfaces readings', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);
    actingAs($nurse)->post(route('queue-entries.vitals', $entry), ['pulse_bpm' => 70]);

    actingAs($nurse)
        ->get(route('queues.show', 'triage'))
        ->assertInertia(fn ($page) => $page
            ->where('servicePoint.captures_vitals', true)
            ->where('entries.0.latest_vitals.pulse_bpm', 70)
        );
});

test('abnormal vitals are flagged with a severity level', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    // Fever (warning) + low SpO2 (critical) -> overall critical.
    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'temperature_c' => 38.6,
        'spo2' => 88,
    ]);

    actingAs(vitalsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertInertia(fn ($page) => $page
            ->where('vitals.0.alert_level', 'critical')
            ->where('vitals.0.flags', fn ($flags) => collect($flags)->pluck('label')->contains('Fever')
                && collect($flags)->pluck('label')->contains('Low SpO₂')
            )
        );
});

test('normal vitals carry no flags', function () {
    $nurse = vitalsUser(['nurse']);
    $entry = inServiceEntry('triage', $nurse);

    actingAs($nurse)->post(route('queue-entries.vitals', $entry), [
        'temperature_c' => 36.8,
        'pulse_bpm' => 72,
        'spo2' => 98,
        'systolic_bp' => 118,
        'diastolic_bp' => 76,
    ]);

    actingAs(vitalsUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertInertia(fn ($page) => $page
            ->where('vitals.0.alert_level', 'normal')
            ->where('vitals.0.flags', [])
        );
});

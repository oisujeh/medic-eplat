<?php

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Models\Visit;
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
function flowUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function servicePoint(string $slug): ServicePoint
{
    return ServicePoint::where('slug', $slug)->firstOrFail();
}

test('records can route a patient to a service point, opening a visit', function () {
    $patient = Patient::factory()->create();
    $triage = servicePoint('triage');

    actingAs(flowUser(['records-officer']))
        ->post(route('patients.route', $patient), [
            'service_point_id' => $triage->id,
            'priority' => 'normal',
            'visit_reason' => 'New visit',
            'note' => 'Take vitals',
        ])
        ->assertRedirect(route('patients.show', $patient))
        ->assertSessionHasNoErrors();

    $visit = Visit::first();
    expect($visit)->not->toBeNull();
    expect($visit->status)->toBe(VisitStatus::Open);
    expect($visit->visit_number)->toStartWith('V/');

    $entry = QueueEntry::first();
    expect($entry->service_point_id)->toBe($triage->id);
    expect($entry->status)->toBe(QueueStatus::Waiting);
    expect($entry->visit_id)->toBe($visit->id);
});

test('routing an already-visiting patient reuses the open visit', function () {
    $patient = Patient::factory()->create();
    $officer = flowUser(['records-officer']);

    actingAs($officer)->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);
    actingAs($officer)->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('laboratory')->id,
        'priority' => 'normal',
    ]);

    expect(Visit::count())->toBe(1);
    expect(QueueEntry::count())->toBe(2);
});

test('staff without the queues module cannot route', function () {
    $patient = Patient::factory()->create();

    actingAs(flowUser(['inventory-officer']))
        ->post(route('patients.route', $patient), [
            'service_point_id' => servicePoint('triage')->id,
            'priority' => 'normal',
        ])
        ->assertForbidden();
});

test('only staff of a service point can view its worklist', function () {
    // Triage is governed by the nursing module.
    actingAs(flowUser(['nurse']))
        ->get(route('queues.show', 'triage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('queues/Show'));

    // A records officer has the queues module but not nursing.
    actingAs(flowUser(['records-officer']))
        ->get(route('queues.show', 'triage'))
        ->assertForbidden();
});

test('a nurse can call a waiting patient', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);

    $nurse = flowUser(['nurse']);
    $entry = QueueEntry::first();

    actingAs($nurse)->post(route('queue-entries.call', $entry))->assertRedirect();

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::InService);
    expect($entry->assigned_to)->toBe($nurse->id);
    expect($entry->started_at)->not->toBeNull();
});

test('completing an entry can route the patient onward', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);

    $nurse = flowUser(['nurse']);
    $entry = QueueEntry::first();
    actingAs($nurse)->post(route('queue-entries.call', $entry));

    actingAs($nurse)->post(route('queue-entries.complete', $entry), [
        'next_service_point_id' => servicePoint('consultation')->id,
        'next_priority' => 'urgent',
        'next_note' => 'For review',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Completed);
    expect($entry->completed_at)->not->toBeNull();

    $onward = QueueEntry::where('service_point_id', servicePoint('consultation')->id)->first();
    expect($onward)->not->toBeNull();
    expect($onward->status)->toBe(QueueStatus::Waiting);
    expect($onward->visit_id)->toBe($entry->visit_id);
});

test('a nurse cannot act on another service point queue entry', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('laboratory')->id,
        'priority' => 'normal',
    ]);

    // Nurse has nursing, not laboratory — cannot call a lab queue entry.
    actingAs(flowUser(['nurse']))
        ->post(route('queue-entries.call', QueueEntry::first()))
        ->assertForbidden();
});

test('closing a visit cancels active entries', function () {
    $patient = Patient::factory()->create();
    $officer = flowUser(['records-officer']);
    actingAs($officer)->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);

    $visit = Visit::first();

    actingAs($officer)->post(route('visits.close', $visit))
        ->assertRedirect(route('patients.show', $patient));

    $visit->refresh();
    expect($visit->status)->toBe(VisitStatus::Closed);
    expect(QueueEntry::first()->status)->toBe(QueueStatus::Cancelled);
});

test('the queues overview is available to queue staff', function () {
    actingAs(flowUser(['nurse']))
        ->get(route('queues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('queues/Index'));
});

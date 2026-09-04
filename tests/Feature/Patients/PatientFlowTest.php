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

test('a queue can be managed by its own staff and by all-module roles', function () {
    // Triage is governed by the nursing module.
    actingAs(flowUser(['nurse']))
        ->get(route('queues.show', 'triage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('queues/Show')
            ->where('servicePoint.console_url', '/nursing')
        );

    actingAs(flowUser(['super-administrator']))
        ->get(route('queues.show', 'triage'))
        ->assertOk();

    // Records staff route patients but do not manage other points' queues.
    actingAs(flowUser(['records-officer']))
        ->get(route('queues.show', 'triage'))
        ->assertForbidden();
});

test('a queue entry can be reassigned to eligible staff or returned to the pool', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);

    $nurse = flowUser(['nurse']);
    $charge = flowUser(['nurse']);
    $entry = QueueEntry::first();

    actingAs($charge)->patch(route('queue-entries.assign', $entry), ['assigned_to' => $nurse->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($entry->fresh()->assigned_to)->toBe($nurse->id);

    // A cashier does not work triage.
    actingAs($charge)->patch(route('queue-entries.assign', $entry), ['assigned_to' => flowUser(['cashier'])->id])
        ->assertSessionHasErrors('assigned_to');

    actingAs($charge)->patch(route('queue-entries.assign', $entry), ['assigned_to' => null])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($entry->fresh()->assigned_to)->toBeNull();

    // Records staff cannot manage a nursing queue.
    actingAs(flowUser(['records-officer']))
        ->patch(route('queue-entries.assign', $entry), ['assigned_to' => null])
        ->assertForbidden();
});

test('a misrouted entry can be re-routed to another service point', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('triage')->id,
        'priority' => 'normal',
    ]);

    $entry = QueueEntry::first();

    actingAs(flowUser(['nurse']))->post(route('queue-entries.reroute', $entry), [
        'service_point_id' => servicePoint('consultation')->id,
        'priority' => 'urgent',
        'note' => 'Booked straight to clinic',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Cancelled)
        ->and($entry->note)->toContain('Re-routed to General Consultation');

    $onward = QueueEntry::where('service_point_id', servicePoint('consultation')->id)->first();
    expect($onward)->not->toBeNull();
    expect($onward->status)->toBe(QueueStatus::Waiting);
    expect($onward->priority->value)->toBe('urgent');
    expect($onward->visit_id)->toBe($entry->visit_id);
});

test('staff outside a service point cannot manage its entries', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('laboratory')->id,
        'priority' => 'normal',
    ]);

    // Nurse has nursing, not laboratory.
    actingAs(flowUser(['nurse']))
        ->post(route('queue-entries.cancel', QueueEntry::first()))
        ->assertForbidden();
});

test('a patient cannot be completed from the queue without documentation', function () {
    $patient = Patient::factory()->create();
    actingAs(flowUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => servicePoint('consultation')->id,
        'priority' => 'normal',
    ]);

    // The old shortcut is gone; sign-off lives on the encounter.
    actingAs(flowUser(['physician']))
        ->post('/queue-entries/'.QueueEntry::first()->id.'/complete')
        ->assertNotFound();
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

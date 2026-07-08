<?php

use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
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
function assignUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function assignSp(string $slug): ServicePoint
{
    return ServicePoint::where('slug', $slug)->firstOrFail();
}

test('routing can assign the entry to a specific staff member', function () {
    $patient = Patient::factory()->create();
    $nurse = assignUser(['nurse']);

    actingAs(assignUser(['records-officer']))
        ->post(route('patients.route', $patient), [
            'service_point_id' => assignSp('triage')->id,
            'assigned_to' => $nurse->id,
            'priority' => 'normal',
        ])
        ->assertSessionHasNoErrors();

    expect(QueueEntry::first()->assigned_to)->toBe($nurse->id);
});

test('an assignee ineligible for the service point is ignored', function () {
    $patient = Patient::factory()->create();
    $pharmacist = assignUser(['pharmacy-staff']);

    actingAs(assignUser(['records-officer']))
        ->post(route('patients.route', $patient), [
            'service_point_id' => assignSp('triage')->id,
            'assigned_to' => $pharmacist->id,
            'priority' => 'normal',
        ]);

    // Pharmacist can't work Triage (nursing) — entry falls back to the pool.
    expect(QueueEntry::first()->assigned_to)->toBeNull();
});

test('personnel see their assigned patients plus the unassigned pool', function () {
    $records = assignUser(['records-officer']);
    $nurseA = assignUser(['nurse']);
    $nurseB = assignUser(['nurse']);

    $assigned = Patient::factory()->create(['surname' => 'Assigned', 'first_name' => 'Ada']);
    $pool = Patient::factory()->create(['surname' => 'Pool', 'first_name' => 'Ben']);

    actingAs($records)->post(route('patients.route', $assigned), [
        'service_point_id' => assignSp('triage')->id,
        'assigned_to' => $nurseA->id,
        'priority' => 'normal',
    ]);
    actingAs($records)->post(route('patients.route', $pool), [
        'service_point_id' => assignSp('triage')->id,
        'priority' => 'normal',
    ]);

    // Nurse A sees both (their assignment + the pool).
    actingAs($nurseA)->get(route('queues.show', 'triage'))
        ->assertInertia(fn ($page) => $page->has('entries', 2));

    // Nurse B sees only the unassigned pool patient.
    actingAs($nurseB)->get(route('queues.show', 'triage'))
        ->assertInertia(fn ($page) => $page
            ->has('entries', 1)
            ->where('entries.0.patient.name', fn ($n) => str_contains($n, 'Pool'))
        );
});

test('a full-access user sees the entire queue', function () {
    $records = assignUser(['records-officer']);
    $nurseA = assignUser(['nurse']);

    actingAs($records)->post(route('patients.route', Patient::factory()->create()), [
        'service_point_id' => assignSp('triage')->id,
        'assigned_to' => $nurseA->id,
        'priority' => 'normal',
    ]);
    actingAs($records)->post(route('patients.route', Patient::factory()->create()), [
        'service_point_id' => assignSp('triage')->id,
        'priority' => 'normal',
    ]);

    actingAs(assignUser(['super-administrator']))->get(route('queues.show', 'triage'))
        ->assertInertia(fn ($page) => $page->has('entries', 2)->where('seesAll', true));
});

test('routing onward can assign the next personnel', function () {
    $patient = Patient::factory()->create();
    $records = assignUser(['records-officer']);
    $nurse = assignUser(['nurse']);
    $physician = assignUser(['physician']);

    actingAs($records)->post(route('patients.route', $patient), [
        'service_point_id' => assignSp('triage')->id,
        'priority' => 'normal',
    ]);

    $entry = QueueEntry::first();
    actingAs($nurse)->post(route('queue-entries.call', $entry));

    actingAs($nurse)->post(route('queue-entries.complete', $entry), [
        'next_service_point_id' => assignSp('consultation')->id,
        'next_assigned_to' => $physician->id,
        'next_priority' => 'normal',
    ]);

    $onward = QueueEntry::where('service_point_id', assignSp('consultation')->id)->first();
    expect($onward->assigned_to)->toBe($physician->id);
});

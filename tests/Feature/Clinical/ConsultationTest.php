<?php

use App\Enums\QueueStatus;
use App\Models\Encounter;
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
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('clinical/Consultation'));

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::InService);
    expect($entry->assigned_to)->toBe($physician->id);
    expect(Encounter::where('queue_entry_id', $entry->id)->exists())->toBeTrue();
});

test('a consultation can be saved as a draft', function () {
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);
    actingAs($physician)->get(route('clinical.consult', $entry));

    actingAs($physician)->post(route('clinical.save', $entry), [
        'presenting_complaint' => 'Headache for 3 days',
        'diagnosis' => 'Tension headache',
    ])->assertRedirect();

    $encounter = Encounter::where('queue_entry_id', $entry->id)->first();
    expect($encounter->presenting_complaint)->toBe('Headache for 3 days');
    expect($encounter->status)->toBe(Encounter::STATUS_OPEN);
});

test('completing a consultation requires a diagnosis', function () {
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);
    actingAs($physician)->get(route('clinical.consult', $entry));

    actingAs($physician)->post(route('clinical.complete', $entry), ['diagnosis' => ''])
        ->assertSessionHasErrors('diagnosis');
});

test('completing signs off the encounter and routes the patient onward', function () {
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);
    actingAs($physician)->get(route('clinical.consult', $entry));

    $pharmacy = ServicePoint::where('slug', 'pharmacy')->firstOrFail();

    actingAs($physician)->post(route('clinical.complete', $entry), [
        'diagnosis' => 'Malaria',
        'plan' => 'Antimalarials, review in 3 days',
        'next_service_point_id' => $pharmacy->id,
        'next_priority' => 'normal',
    ])->assertRedirect(route('clinical.index'));

    $entry->refresh();
    expect($entry->status)->toBe(QueueStatus::Completed);

    $encounter = Encounter::where('queue_entry_id', $entry->id)->first();
    expect($encounter->status)->toBe(Encounter::STATUS_COMPLETED);
    expect($encounter->diagnosis)->toBe('Malaria');

    expect(
        QueueEntry::where('service_point_id', $pharmacy->id)
            ->where('status', QueueStatus::Waiting)
            ->exists()
    )->toBeTrue();
});

test('completed encounters appear on the patient profile', function () {
    $entry = routeTo('consultation');
    $physician = clinUser(['physician']);
    actingAs($physician)->get(route('clinical.consult', $entry));
    actingAs($physician)->post(route('clinical.complete', $entry), ['diagnosis' => 'Hypertension']);

    actingAs(clinUser(['records-officer']))
        ->get(route('patients.show', $entry->patient_id))
        ->assertInertia(fn ($page) => $page
            ->has('encounters', 1)
            ->where('encounters.0.diagnosis', 'Hypertension')
        );
});

test('a clinician cannot document a non-clinical queue entry', function () {
    $labEntry = routeTo('laboratory');

    actingAs(clinUser(['physician']))
        ->get(route('clinical.consult', $labEntry))
        ->assertNotFound();
});

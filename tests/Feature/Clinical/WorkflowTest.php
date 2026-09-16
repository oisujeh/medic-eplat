<?php

use App\Enums\EncounterType;
use App\Models\BillCharge;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServiceChargesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Database\Seeders\WorkflowsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(ServiceChargesSeeder::class);
    $this->seed(WorkflowsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function pathwayUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function pointNamed(string $slug): ServicePoint
{
    return ServicePoint::where('slug', $slug)->firstOrFail();
}

/**
 * Route a new patient to a service point and return the queue entry.
 *
 * @param  array<string, mixed>  $extra
 */
function sendTo(string $slug, array $extra = []): QueueEntry
{
    $patient = Patient::factory()->create();

    actingAs(pathwayUser(['records-officer']))
        ->post(route('patients.route', $patient), ['service_point_id' => pointNamed($slug)->id, 'priority' => 'normal', ...$extra])
        ->assertSessionHasNoErrors();

    return QueueEntry::latest('id')->firstOrFail();
}

// ------------------------------------------------------------- Pathway choice

test('the pathways are seeded with their steps and moves', function () {
    $opd = Workflow::where('slug', 'opd')->firstOrFail();

    expect(Workflow::pluck('slug')->all())->toContain('opd', 'emergency', 'anc', 'immunization')
        ->and($opd->is_default)->toBeTrue()
        ->and($opd->stages->count())->toBe(5)
        ->and($opd->stageAt(pointNamed('consultation'))->charge_code)->toBe('CONSULTATION')
        ->and($opd->stageAt(pointNamed('consultation'))->encounter_type)->toBe(EncounterType::Consultation)
        ->and($opd->stageAt(pointNamed('triage'))->transitions->pluck('to.service_point_id')->all())->toBe([pointNamed('consultation')->id]);
});

test('a visit takes the pathway that starts where the patient is sent', function () {
    $triage = sendTo('triage');
    $anc = sendTo('anc');
    $emergency = sendTo('emergency');
    $lab = sendTo('laboratory');

    expect($triage->visit->workflow->slug)->toBe('opd')
        ->and($triage->stage->service_point_id)->toBe(pointNamed('triage')->id)
        ->and($anc->visit->workflow->slug)->toBe('anc')
        ->and($emergency->visit->workflow->slug)->toBe('emergency')
        // No pathway starts at the laboratory: the default one applies.
        ->and($lab->visit->workflow->slug)->toBe('opd')
        ->and($lab->stage->service_point_id)->toBe(pointNamed('laboratory')->id);
});

test('the desk can choose the pathway explicitly', function () {
    $emergency = Workflow::where('slug', 'emergency')->firstOrFail();
    $entry = sendTo('laboratory', ['workflow_id' => $emergency->id]);

    expect($entry->visit->workflow_id)->toBe($emergency->id);
});

// --------------------------------------------------------- Stage behaviour

test('the step decides the encounter type: emergency opens a consultation', function () {
    $physician = pathwayUser(['physician']);
    $entry = sendTo('emergency');

    $encounter = openEncounter($entry, $physician);

    expect($encounter->type)->toBe(EncounterType::Consultation);
});

test('signing offers only the moves the pathway allows, suggested first', function () {
    $physician = pathwayUser(['physician']);
    $encounter = openEncounter(sendTo('consultation'), $physician);

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertInertia(fn ($page) => $page
            ->where('onwardServicePoints', function ($options) {
                $options = collect($options);

                return $options->pluck('name')->all() === ['Pharmacy', 'Laboratory', 'Billing / Cashier']
                    && $options->first()['is_default'] === true;
            }));
});

test('a move the pathway does not allow is refused, and an allowed one posts the step fee', function () {
    $physician = pathwayUser(['physician']);
    $encounter = openEncounter(sendTo('consultation'), $physician);

    $payload = ['assessment' => 'Uncomplicated malaria', 'plan' => 'ACT for three days', 'outcome' => 'home'];

    actingAs($physician)
        ->post(route('encounters.sign', $encounter), [...$payload, 'next_service_point_id' => pointNamed('triage')->id])
        ->assertSessionHasErrors('next_service_point_id');

    expect($encounter->fresh()->isSigned())->toBeFalse();

    actingAs($physician)
        ->post(route('encounters.sign', $encounter), [...$payload, 'next_service_point_id' => pointNamed('pharmacy')->id])
        ->assertSessionHasNoErrors();

    $next = QueueEntry::latest('id')->firstOrFail();

    expect($encounter->fresh()->isSigned())->toBeTrue()
        ->and($next->service_point_id)->toBe(pointNamed('pharmacy')->id)
        ->and($next->stage->service_point_id)->toBe(pointNamed('pharmacy')->id)
        ->and(BillCharge::where('source', BillCharge::SOURCE_CONSULTATION)->where('description', 'like', 'General Consultation%')->exists())->toBeTrue();
});

test('a nursing step with no fee posts nothing on signing', function () {
    $nurse = pathwayUser(['nurse']);
    $encounter = openEncounter(sendTo('anc'), $nurse, 'nursing.workspace');

    actingAs($nurse)
        ->post(route('encounters.sign', $encounter), ['plan' => 'Routine antenatal review.', 'next_service_point_id' => pointNamed('laboratory')->id])
        ->assertSessionHasNoErrors();

    expect(BillCharge::count())->toBe(0)
        ->and(QueueEntry::latest('id')->first()->service_point_id)->toBe(pointNamed('laboratory')->id);
});

test('without a pathway the flow behaves as before', function () {
    Workflow::query()->delete();

    $physician = pathwayUser(['physician']);
    $entry = sendTo('consultation');
    $encounter = openEncounter($entry, $physician);

    expect($entry->visit->workflow_id)->toBeNull()
        ->and(app(WorkflowEngine::class)->nextOptions($entry->visit, $entry->servicePoint))->toBeNull()
        ->and($encounter->type)->toBe(EncounterType::Consultation);

    actingAs($physician)
        ->post(route('encounters.sign', $encounter), ['assessment' => 'Tension headache', 'plan' => 'Analgesia', 'outcome' => 'home', 'next_service_point_id' => pointNamed('triage')->id])
        ->assertSessionHasNoErrors();

    expect(BillCharge::where('source', BillCharge::SOURCE_CONSULTATION)->exists())->toBeTrue();
});

test('the chart offers the pathways with their starting points', function () {
    $patient = Patient::factory()->create();

    actingAs(pathwayUser(['records-officer']))
        ->get(route('patients.show', $patient))
        ->assertInertia(fn ($page) => $page
            ->where('routeOptions.workflows', fn ($workflows) => collect($workflows)->firstWhere('is_default', true)['name'] === 'Outpatient (OPD)'
                && in_array(pointNamed('triage')->id, collect($workflows)->firstWhere('is_default', true)['entry_points'], true)));
});

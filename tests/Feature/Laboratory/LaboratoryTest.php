<?php

use App\Enums\LabOrderStatus;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\LabWorkflowService;
use Database\Seeders\LabCompendiumSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(LabCompendiumSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function labStaff(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Place a requisition for FBC (a 6-analyte panel) plus Malaria Parasite.
 */
function seedLabOrder(): LabOrder
{
    $patient = Patient::factory()->create();
    $tests = LabTest::whereIn('code', ['FBC', 'MP'])->get();

    return app(LabWorkflowService::class)->createOrder($patient, labStaff(['physician']), $tests);
}

test('the compendium seeds tests and panels', function () {
    expect(LabTest::count())->toBeGreaterThan(20);
    expect(LabTest::where('code', 'FBC')->first()->components()->count())->toBe(6);
});

test('lab staff can open the worklist', function () {
    actingAs(labStaff(['laboratory-staff']))
        ->get(route('laboratory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('laboratory/Index'));
});

test('staff without the laboratory module are forbidden', function () {
    actingAs(labStaff(['records-officer']))
        ->get(route('laboratory.index'))
        ->assertForbidden();
});

test('ordering expands a panel into component result lines with an accession', function () {
    $order = seedLabOrder();

    expect($order->results()->count())->toBe(7); // FBC (6) + MP (1)
    expect($order->status)->toBe(LabOrderStatus::Ordered);
    expect($order->accession_number)->toStartWith('LAB/');
    expect($order->results()->where('code', 'HB')->exists())->toBeTrue();
});

test('the order screen renders with its result lines', function () {
    $order = seedLabOrder();

    actingAs(labStaff(['laboratory-staff']))
        ->get(route('laboratory.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Order')
            ->where('order.accession_number', $order->accession_number)
            ->where('order.status', 'ordered')
            ->has('results', 7)
            ->has('flags')
            ->where('patient.name', $order->patient->fullName())
        );
});

test('a specimen is collected, received, resulted with auto-flagging and verified', function () {
    $order = seedLabOrder();
    $lab = labStaff(['laboratory-staff']);

    actingAs($lab)->post(route('laboratory.collect', $order), ['specimen_type' => 'EDTA blood'])->assertRedirect();
    expect($order->fresh()->status)->toBe(LabOrderStatus::Collected);

    actingAs($lab)->post(route('laboratory.receive', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(LabOrderStatus::InProgress);

    // Enter every value; drive HB below its reference low to trigger a Low flag.
    $entries = [];
    foreach ($order->results as $result) {
        $entries[$result->id] = [
            'value' => $result->reference_low !== null ? (string) ($result->reference_low - 1) : 'Negative',
        ];
    }
    actingAs($lab)->post(route('laboratory.results', $order), ['results' => $entries])->assertRedirect();

    $hb = $order->results()->where('code', 'HB')->first();
    expect($hb->flag)->toBe('low');                       // auto-derived from the range
    expect($hb->status)->toBe(LabResult::STATUS_PENDING); // preliminary until released

    actingAs($lab)->post(route('laboratory.verify', $order))->assertRedirect(route('laboratory.index'));

    $order->refresh();
    expect($order->status)->toBe(LabOrderStatus::Completed);
    expect($order->verified_by)->toBe($lab->id);
    expect($order->results()->where('status', LabResult::STATUS_RESULTED)->count())->toBe(7);
});

test('verification is blocked until every test has a value', function () {
    $order = seedLabOrder();
    $lab = labStaff(['laboratory-staff']);

    actingAs($lab)->post(route('laboratory.collect', $order));
    actingAs($lab)->post(route('laboratory.receive', $order));

    $first = $order->results()->first();
    actingAs($lab)->post(route('laboratory.results', $order), ['results' => [$first->id => ['value' => '5']]]);

    actingAs($lab)->post(route('laboratory.verify', $order))->assertStatus(422);
    expect($order->fresh()->status)->toBe(LabOrderStatus::InProgress);
});

test('collect is rejected unless the order is awaiting collection', function () {
    $order = seedLabOrder();
    $lab = labStaff(['laboratory-staff']);

    actingAs($lab)->post(route('laboratory.collect', $order))->assertRedirect();
    actingAs($lab)->post(route('laboratory.collect', $order))->assertStatus(422);
});

test('an order can be cancelled with a reason', function () {
    $order = seedLabOrder();

    actingAs(labStaff(['laboratory-staff']))
        ->post(route('laboratory.cancel', $order), ['reason' => 'Duplicate request'])
        ->assertRedirect(route('laboratory.index'));

    expect($order->fresh()->status)->toBe(LabOrderStatus::Cancelled);
    expect($order->fresh()->cancelled_reason)->toBe('Duplicate request');
});

test('the worklist can be filtered by department', function () {
    seedLabOrder();

    actingAs(labStaff(['laboratory-staff']))
        ->get(route('laboratory.index', ['department' => 'haematology']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

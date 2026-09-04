<?php

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\BillingService;
use App\Services\LabWorkflowService;
use Database\Seeders\LabCompendiumSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServiceChargesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(ServiceChargesSeeder::class);
    $this->seed(LabCompendiumSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function billUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function routeToPoint(string $slug): QueueEntry
{
    $patient = Patient::factory()->create();
    actingAs(billUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', $slug)->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return QueueEntry::latest('id')->first();
}

/**
 * A bill carrying a single charge of the given value.
 */
function billWithCharge(float $unitPrice): Bill
{
    $bill = Bill::factory()->create(['patient_id' => Patient::factory()]);
    $bill->charges()->create([
        'source' => 'pharmacy',
        'description' => 'Item',
        'quantity' => 1,
        'unit_price' => $unitPrice,
        'total' => $unitPrice,
    ]);

    return $bill;
}

/**
 * Take a lab order all the way to verified.
 */
function verifyLabOrder(LabOrder $order, User $actor): void
{
    $svc = app(LabWorkflowService::class);
    $svc->collect($order, $actor);
    $svc->receive($order, $actor);
    $entries = [];
    foreach ($order->results as $result) {
        $entries[$result->id] = ['value' => '5'];
    }
    $svc->recordResults($order, $entries);
    $svc->verify($order, $actor);
}

// ---- Payments ----

test('a partial payment marks the bill part-paid with a balance', function () {
    $bill = billWithCharge(1000);

    actingAs(billUser(['cashier']))->post(route('billing.pay', $bill), [
        'amount' => 400,
        'method' => 'cash',
    ])->assertRedirect();

    $bill->refresh();
    expect($bill->status)->toBe(BillStatus::PartiallyPaid);
    expect($bill->balance())->toBe(600.0);
});

test('paying the full amount settles the bill', function () {
    $bill = billWithCharge(1000);

    actingAs(billUser(['cashier']))->post(route('billing.pay', $bill), [
        'amount' => 1000,
        'method' => 'transfer',
        'reference' => 'TX-1',
    ])->assertRedirect();

    $bill->refresh();
    expect($bill->status)->toBe(BillStatus::Paid);
    expect($bill->balance())->toBe(0.0);
});

test('a settled bill rejects further payment', function () {
    $bill = billWithCharge(500);
    $cashier = billUser(['cashier']);

    actingAs($cashier)->post(route('billing.pay', $bill), ['amount' => 500, 'method' => 'cash']);
    actingAs($cashier)->post(route('billing.pay', $bill), ['amount' => 100, 'method' => 'cash'])
        ->assertStatus(422);
});

test('a bill can be downloaded as a PDF invoice', function () {
    $bill = billWithCharge(1500);
    app(BillingService::class)->recordPayment(
        $bill, 500, PaymentMethod::Cash, 'RCP-1', billUser(['cashier']),
    );

    $response = actingAs(billUser(['cashier']))->get(route('billing.invoice', $bill));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF'); // valid PDF signature
});

// ---- Fee schedule ----

test('the fee schedule can be viewed and managed', function () {
    $cashier = billUser(['cashier']);

    actingAs($cashier)->get(route('billing.services.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('billing/Services')->has('services'));

    actingAs($cashier)->post(route('billing.services.store'), [
        'code' => 'XRAY', 'name' => 'X-Ray', 'category' => 'procedure', 'unit' => 'each', 'price' => 4500,
    ])->assertRedirect();

    $service = ServiceCharge::where('code', 'XRAY')->first();
    expect($service->price)->toBe(4500.0);

    actingAs($cashier)->patch(route('billing.services.update', $service), [
        'name' => 'X-Ray', 'category' => 'procedure', 'unit' => 'each', 'price' => 6000, 'is_active' => true,
    ])->assertRedirect();
    expect($service->fresh()->price)->toBe(6000.0);
});

test('a fee-schedule service can be charged to a bill', function () {
    $bill = billWithCharge(1000);
    $bed = ServiceCharge::where('code', 'BED-ICU')->first(); // 25,000 / day

    actingAs(billUser(['cashier']))->post(route('billing.charge', $bill), [
        'service_charge_id' => $bed->id,
        'quantity' => 3,
    ])->assertRedirect();

    $bill->refresh();
    expect($bill->total())->toBe(76000.0); // 1,000 + 25,000×3
    $charge = $bill->charges()->where('source', 'bed')->first();
    expect($charge->quantity)->toBe(3);
    expect($charge->total)->toBe(75000.0);
});

test('a custom charge can be added to a bill', function () {
    $bill = billWithCharge(500);

    actingAs(billUser(['cashier']))->post(route('billing.charge', $bill), [
        'description' => 'Ambulance service',
        'unit_price' => 8000,
        'quantity' => 1,
    ])->assertRedirect();

    expect($bill->refresh()->total())->toBe(8500.0);
    expect($bill->charges()->where('source', 'other')->where('description', 'Ambulance service')->exists())->toBeTrue();
});

test('the consultation fee comes from the editable fee schedule', function () {
    ServiceCharge::where('code', ServiceCharge::CONSULTATION)->update(['price' => 3500]);

    $entry = routeToPoint('consultation');
    $physician = billUser(['physician']);
    $encounter = openEncounter($entry, $physician);
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'X']);

    $charge = Bill::where('visit_id', $entry->visit_id)->first()->charges()->where('source', 'consultation')->first();
    expect($charge->total)->toBe(3500.0);
});

// ---- Multi-source charges ----

test('verifying a lab order posts itemised charges to the visit bill', function () {
    $entry = routeToPoint('laboratory');
    $lab = billUser(['laboratory-staff']);

    $order = app(LabWorkflowService::class)->createOrder(
        $entry->patient,
        $lab,
        LabTest::whereIn('code', ['FBC'])->get(),
        visit: $entry->visit,
        queueEntry: $entry,
    );

    verifyLabOrder($order, $lab);

    $bill = Bill::where('visit_id', $entry->visit_id)->first();
    expect($bill)->not->toBeNull();
    expect($bill->charges()->where('source', 'laboratory')->count())->toBe(6); // FBC analytes
    expect($bill->total())->toBe(2550.0); // 450×3 + 400×3
});

test('completing a consultation posts the consultation fee', function () {
    $entry = routeToPoint('consultation');
    $physician = billUser(['physician']);

    $encounter = openEncounter($entry, $physician);
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'Malaria'])
        ->assertRedirect();

    $charge = Bill::where('visit_id', $entry->visit_id)->first()?->charges()->where('source', 'consultation')->first();
    expect($charge)->not->toBeNull();
    expect($charge->total)->toBe(2000.0);
});

test('charges from consultation and laboratory land on one running bill', function () {
    $entry = routeToPoint('consultation');
    $physician = billUser(['physician']);

    $encounter = openEncounter($entry, $physician);
    actingAs($physician)->post(route('encounters.sign', $encounter), ['assessment' => 'HIV']);

    // A lab order on the same visit, verified.
    $lab = billUser(['laboratory-staff']);
    $order = app(LabWorkflowService::class)->createOrder(
        $entry->patient,
        $lab,
        LabTest::whereIn('code', ['CD4'])->get(), // 8000
        visit: $entry->visit,
    );
    verifyLabOrder($order, $lab);

    expect(Bill::where('visit_id', $entry->visit_id)->count())->toBe(1);
    $bill = Bill::where('visit_id', $entry->visit_id)->first();
    expect($bill->charges()->where('source', 'consultation')->exists())->toBeTrue();
    expect($bill->charges()->where('source', 'laboratory')->exists())->toBeTrue();
    expect($bill->total())->toBe(10000.0); // 2000 + 8000
});

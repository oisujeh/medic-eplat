<?php

use App\Enums\MedicationFulfilment;
use App\Models\Bill;
use App\Models\Dispense;
use App\Models\DomainEventRecord;
use App\Models\Encounter;
use App\Models\InventoryItem;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockLocations;
use Database\Seeders\PharmacyStockSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(PharmacyStockSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function pharmUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Register a patient and route them to the pharmacy queue.
 */
function routeToPharmacy(): QueueEntry
{
    $patient = Patient::factory()->create();
    actingAs(pharmUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'pharmacy')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return QueueEntry::latest('id')->first();
}

// ---- Inventory ----

test('the formulary seeds stocked items', function () {
    expect(InventoryItem::count())->toBeGreaterThan(10);
    expect(InventoryItem::where('code', 'DRG-0001')->first()->quantity_on_hand)->toBe(500);
});

test('inventory staff can open the store and receive stock', function () {
    $item = InventoryItem::factory()->create(['quantity_on_hand' => 0]);
    $officer = pharmUser(['inventory-officer']);

    actingAs($officer)->get(route('inventory.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inventory/Index'));

    actingAs($officer)->post(route('inventory.receive', $item), [
        'quantity' => 100,
        'batch_number' => 'B-1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
    ])->assertRedirect();

    expect($item->fresh()->quantity_on_hand)->toBe(100);
    expect($item->batches()->count())->toBe(1);
    expect($item->movements()->count())->toBe(1);
});

test('issuing stock consumes the earliest-expiry batch first (FEFO)', function () {
    $item = InventoryItem::factory()->create(['quantity_on_hand' => 0]);
    $svc = app(InventoryService::class);
    $actor = pharmUser(['inventory-officer']);

    $svc->receiveStock($item, 30, ['batch_number' => 'LATE', 'expiry_date' => now()->addYear()->toDateString()], $actor);
    $svc->receiveStock($item, 20, ['batch_number' => 'SOON', 'expiry_date' => now()->addMonth()->toDateString()], $actor);
    expect($item->fresh()->quantity_on_hand)->toBe(50);

    $svc->issue($item->fresh(), 25, $actor);

    expect($item->fresh()->quantity_on_hand)->toBe(25);
    expect($item->batches()->where('batch_number', 'SOON')->first()->quantity)->toBe(0);
    expect($item->batches()->where('batch_number', 'LATE')->first()->quantity)->toBe(25);
});

test('a batch cannot be adjusted below zero', function () {
    $item = InventoryItem::factory()->create(['quantity_on_hand' => 0]);
    $officer = pharmUser(['inventory-officer']);
    $batch = app(InventoryService::class)->receiveStock($item, 10, [], $officer);

    actingAs($officer)->post(route('inventory.adjust', $batch), ['delta' => -50])
        ->assertSessionHasErrors('delta');

    expect($item->fresh()->quantity_on_hand)->toBe(10);
});

// ---- Pharmacy dispensing ----

test('pharmacy staff can open the dispensing worklist', function () {
    actingAs(pharmUser(['pharmacy-staff']))->get(route('pharmacy.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('pharmacy/Index'));
});

test('staff without the pharmacy module are forbidden', function () {
    actingAs(pharmUser(['records-officer']))->get(route('pharmacy.index'))->assertForbidden();
});

test('dispensing deducts stock, snapshots the price and bills the visit', function () {
    $entry = routeToPharmacy();
    $pharmacist = pharmUser(['pharmacy-staff']);
    $item = InventoryItem::where('code', 'DRG-0001')->first(); // Paracetamol, sell 8, stock 500

    actingAs($pharmacist)->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => 20]],
    ])->assertRedirect();

    expect($item->fresh()->quantity_on_hand)->toBe(480);

    $dispense = Dispense::where('patient_id', $entry->patient_id)->first();
    $line = $dispense->items()->first();
    expect($line->unit_price)->toBe(8.0);   // snapshot of selling price
    expect($line->total)->toBe(160.0);

    $bill = Bill::where('visit_id', $entry->visit_id)->first();
    expect($bill)->not->toBeNull();
    expect($bill->total())->toBe(160.0);
    $charge = $bill->charges()->first();
    expect($charge->source)->toBe('pharmacy');
    expect($charge->total)->toBe(160.0);
});

test('a later price change does not alter an already-billed charge', function () {
    $entry = routeToPharmacy();
    $item = InventoryItem::where('code', 'DRG-0003')->first(); // Amlodipine, sell 25

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => 4]],
    ])->assertRedirect();

    $item->update(['selling_price' => 999]); // price hike after the fact

    $charge = Bill::where('visit_id', $entry->visit_id)->first()->charges()->first();
    expect($charge->total)->toBe(100.0); // still the price captured at dispense
});

test('dispensing more than is in stock is rejected', function () {
    $entry = routeToPharmacy();
    $item = InventoryItem::where('code', 'DRG-0009')->first(); // Ceftriaxone, stock 60

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => 1000]],
    ])->assertSessionHasErrors('lines.0.quantity');

    expect($item->fresh()->quantity_on_hand)->toBe(60); // untouched
});

test('multiple dispenses in a visit accrue to one running bill', function () {
    $entry = routeToPharmacy();
    $pharmacist = pharmUser(['pharmacy-staff']);
    $a = InventoryItem::where('code', 'DRG-0001')->first(); // 8
    $b = InventoryItem::where('code', 'DRG-0003')->first(); // 25

    actingAs($pharmacist)->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $a->id, 'quantity' => 10]],
    ]);
    actingAs($pharmacist)->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $b->id, 'quantity' => 2]],
    ]);

    expect(Bill::where('visit_id', $entry->visit_id)->count())->toBe(1);
    expect(Bill::where('visit_id', $entry->visit_id)->first()->total())->toBe(130.0); // 80 + 50
});

// ---- Billing ----

test('a bill lists its charges and total', function () {
    $entry = routeToPharmacy();
    $item = InventoryItem::where('code', 'DRG-0003')->first(); // sell 25

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => 4]],
    ]);

    $bill = Bill::where('visit_id', $entry->visit_id)->first();

    actingAs(pharmUser(['cashier']))->get(route('billing.show', $bill))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/Bill')
            ->where('bill.total', 100)
            ->has('charges', 1)
        );
});

// ---- Prescription fulfilment ----

/**
 * Route a patient to consultation and open the encounter as a physician.
 *
 * @return array{0: Encounter, 1: User}
 */
function pharmEncounter(): array
{
    $physician = pharmUser(['physician']);
    $patient = Patient::factory()->create();

    actingAs(pharmUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return [openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician), $physician];
}

test('a prescription can name a stocked product and a quantity to supply', function () {
    [$encounter, $physician] = pharmEncounter();
    $product = InventoryItem::where('code', 'DRG-0002')->first(); // Amoxicillin 500mg

    actingAs($physician)->post(route('encounters.medications.store', $encounter), [
        'name' => 'Amoxicillin',
        'inventory_item_id' => $product->id,
        'dose' => '500mg',
        'frequency' => 'TDS',
        'quantity' => 15,
        'duration_days' => 5,
    ])->assertRedirect();

    $medication = Medication::where('patient_id', $encounter->patient_id)->firstOrFail();
    expect($medication->inventory_item_id)->toBe($product->id)
        ->and($medication->quantity)->toBe(15)
        ->and($medication->duration_days)->toBe(5)
        ->and($medication->fulfilment)->toBe(MedicationFulfilment::Pending)
        ->and($medication->quantity_dispensed)->toBe(0);
});

test('stocked products can be looked up for the prescribing form', function () {
    actingAs(pharmUser(['physician']))->getJson(route('products.search', ['q' => 'Amox']))
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Amoxicillin');

    actingAs(pharmUser(['physician']))->getJson(route('products.search'))
        ->assertOk()
        ->assertJsonCount(0, 'products');
});

test('the dispensing screen lists outstanding prescriptions with the products that fill them', function () {
    $entry = routeToPharmacy();
    $product = InventoryItem::where('code', 'DRG-0001')->first(); // Paracetamol
    Medication::factory()->for($entry->patient)->create([
        'name' => 'Paracetamol', 'inventory_item_id' => $product->id, 'quantity' => 20,
    ]);
    Medication::factory()->for($entry->patient)->create([
        'name' => 'Ibuprofen', 'inventory_item_id' => null, 'concept_id' => null, 'quantity' => null,
    ]);

    actingAs(pharmUser(['pharmacy-staff']))->get(route('pharmacy.dispense', $entry))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pharmacy/Dispense')
            ->where('location.name', 'Dispensary')
            ->has('prescriptions', 2)
            // Newest first among the outstanding: Ibuprofen, matched by name
            // because it carries no concept or product.
            ->where('prescriptions.0.products.0.name', 'Ibuprofen')
            ->where('prescriptions.1.remaining', 20)
            ->where('prescriptions.1.products.0.id', $product->id)
            ->where('prescriptions.1.fulfilment.value', 'pending')
        );
});

test('dispensing against a prescription records fulfilment, partially then in full', function () {
    $entry = routeToPharmacy();
    $pharmacist = pharmUser(['pharmacy-staff']);
    $product = InventoryItem::where('code', 'DRG-0001')->first();
    $medication = Medication::factory()->for($entry->patient)->create([
        'name' => 'Paracetamol', 'inventory_item_id' => $product->id, 'quantity' => 30,
    ]);

    actingAs($pharmacist)->get(route('pharmacy.index'))
        ->assertInertia(fn ($page) => $page->where('queue.0.pending_scripts', 1));

    actingAs($pharmacist)->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 10, 'medication_id' => $medication->id]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($medication->fresh()->fulfilment)->toBe(MedicationFulfilment::Partial)
        ->and($medication->fresh()->quantity_dispensed)->toBe(10)
        ->and($medication->fresh()->quantityRemaining())->toBe(20);

    actingAs($pharmacist)->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 20, 'medication_id' => $medication->id]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($medication->fresh()->fulfilment)->toBe(MedicationFulfilment::Dispensed)
        ->and($medication->fresh()->quantity_dispensed)->toBe(30)
        ->and($medication->dispenseItems()->count())->toBe(2);

    actingAs($pharmacist)->get(route('pharmacy.index'))
        ->assertInertia(fn ($page) => $page->where('queue.0.pending_scripts', 0));
});

test('a prescription refuses more than it asked for', function () {
    $entry = routeToPharmacy();
    $product = InventoryItem::where('code', 'DRG-0001')->first();
    $medication = Medication::factory()->for($entry->patient)->create([
        'name' => 'Paracetamol', 'inventory_item_id' => $product->id, 'quantity' => 30,
    ]);

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 40, 'medication_id' => $medication->id]],
    ])->assertSessionHasErrors('lines');

    expect($product->fresh()->quantity_on_hand)->toBe(500)
        ->and($medication->fresh()->quantity_dispensed)->toBe(0)
        ->and(Dispense::count())->toBe(0);
});

test('a prescription written for another patient cannot be filled here', function () {
    $entry = routeToPharmacy();
    $product = InventoryItem::where('code', 'DRG-0001')->first();
    $other = Medication::factory()->create(['name' => 'Paracetamol', 'quantity' => 10]);

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 5, 'medication_id' => $other->id]],
    ])->assertSessionHasErrors('lines.0.medication_id');
});

test('a prescription can be marked as sourced outside and put back', function () {
    $entry = routeToPharmacy();
    $pharmacist = pharmUser(['pharmacy-staff']);
    $medication = Medication::factory()->for($entry->patient)->create(['name' => 'Insulin glargine', 'quantity' => 1]);

    actingAs($pharmacist)->post(route('pharmacy.prescriptions.external', [$entry, $medication]))->assertRedirect();
    expect($medication->fresh()->fulfilment)->toBe(MedicationFulfilment::External);

    actingAs($pharmacist)->post(route('pharmacy.prescriptions.reopen', [$entry, $medication]))->assertRedirect();
    expect($medication->fresh()->fulfilment)->toBe(MedicationFulfilment::Pending);
});

test('dispensing draws from the dispensary and journals a domain event', function () {
    $entry = routeToPharmacy();
    $product = InventoryItem::where('code', 'DRG-0001')->first(); // 300 in store, 200 at dispensary
    $locations = app(StockLocations::class);

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 25]],
    ])->assertRedirect();

    expect($product->quantityAt($locations->dispensary()))->toBe(175)
        ->and($product->quantityAt($locations->receiving()))->toBe(300)
        ->and($product->fresh()->quantity_on_hand)->toBe(475);

    $event = DomainEventRecord::where('name', 'medication_dispensed')->firstOrFail();
    expect($event->patient_id)->toBe($entry->patient_id)
        ->and($event->payload['items'][0]['quantity'])->toBe(25);
});

test('the dispensary cannot dispense what only the main store holds', function () {
    $entry = routeToPharmacy();
    $product = InventoryItem::where('code', 'DRG-0009')->first(); // Ceftriaxone: 36 in store, 24 at dispensary

    actingAs(pharmUser(['pharmacy-staff']))->post(route('pharmacy.dispense.store', $entry), [
        'lines' => [['inventory_item_id' => $product->id, 'quantity' => 30]],
    ])->assertSessionHasErrors('lines.0.quantity');

    expect($product->fresh()->quantity_on_hand)->toBe(60);
});

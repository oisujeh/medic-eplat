<?php

use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\StockRequisition;
use App\Models\User;
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
function reqUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function store(): StockLocation
{
    return app(StockLocations::class)->receiving();
}

function dispensary(): StockLocation
{
    return app(StockLocations::class)->dispensary();
}

/**
 * Raise a requisition from the dispensary to the main store.
 *
 * @param  array<int, array{inventory_item_id: int, quantity: int}>  $lines
 */
function raise(array $lines, ?User $by = null): StockRequisition
{
    actingAs($by ?? reqUser(['pharmacy-staff']))->post(route('inventory.requisitions.store'), [
        'from_location_id' => store()->id,
        'to_location_id' => dispensary()->id,
        'lines' => $lines,
    ])->assertRedirect();

    return StockRequisition::latest('id')->firstOrFail();
}

test('the store seeds a main store and a dispensary linked to the pharmacy', function () {
    expect(store()->slug)->toBe(StockLocation::MAIN_STORE)
        ->and(dispensary()->slug)->toBe(StockLocation::DISPENSARY)
        ->and(dispensary()->servicePoint->slug)->toBe('pharmacy');

    $paracetamol = InventoryItem::where('code', 'DRG-0001')->firstOrFail();
    expect($paracetamol->quantityAt(store()))->toBe(300)
        ->and($paracetamol->quantityAt(dispensary()))->toBe(200)
        ->and($paracetamol->quantity_on_hand)->toBe(500);
});

test('pharmacy staff can raise a requisition on the main store', function () {
    $item = InventoryItem::where('code', 'DRG-0001')->firstOrFail();

    actingAs(reqUser(['pharmacy-staff']))->get(route('inventory.requisitions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('inventory/requisitions/Index'));

    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 100]]);

    expect($requisition->number)->toStartWith('REQ/'.now()->year.'/')
        ->and($requisition->status)->toBe(RequisitionStatus::Requested)
        ->and($requisition->lines()->count())->toBe(1)
        ->and($requisition->toLocation->is(dispensary()))->toBeTrue();

    actingAs(reqUser(['pharmacy-staff']))->get(route('inventory.requisitions.show', $requisition))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/requisitions/Show')
            ->where('requisition.number', $requisition->number)
            ->where('lines.0.available_at_source', 300)
            ->where('can.approve', false)
            ->where('can.cancel', true)
        );
});

test('a requisition needs two different locations and at least one line', function () {
    actingAs(reqUser(['pharmacy-staff']))->post(route('inventory.requisitions.store'), [
        'from_location_id' => store()->id,
        'to_location_id' => store()->id,
        'lines' => [],
    ])->assertSessionHasErrors(['to_location_id', 'lines']);
});

test('approving and issuing moves stock from the store to the dispensary', function () {
    $item = InventoryItem::where('code', 'DRG-0001')->firstOrFail();
    $officer = reqUser(['inventory-officer']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 100]]);

    actingAs($officer)->post(route('inventory.requisitions.approve', $requisition), [
        'quantities' => [$requisition->lines->first()->id => 80],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and($requisition->lines()->first()->quantity_approved)->toBe(80);

    actingAs($officer)->post(route('inventory.requisitions.issue', $requisition))
        ->assertRedirect()->assertSessionHasNoErrors();

    $requisition->refresh();
    expect($requisition->status)->toBe(RequisitionStatus::Issued)
        ->and($requisition->lines()->first()->quantity_issued)->toBe(80)
        ->and($item->quantityAt(store()))->toBe(220)
        ->and($item->quantityAt(dispensary()))->toBe(280)
        ->and($item->fresh()->quantity_on_hand)->toBe(500);

    // The moved stock keeps its batch identity at the destination.
    $arrived = $item->batches()->where('stock_location_id', dispensary()->id)->get();
    expect($arrived->pluck('batch_number')->unique()->all())->toBe(['OPEN-'.$item->id]);

    $movements = $item->movements()->where('reference_id', $requisition->id)->get();
    expect($movements->firstWhere('type', StockMovementType::TransferOut)->quantity_change)->toBe(-80)
        ->and($movements->firstWhere('type', StockMovementType::TransferIn)->quantity_change)->toBe(80);
});

test('confirming a short receipt writes the difference off at the dispensary', function () {
    $item = InventoryItem::where('code', 'DRG-0003')->firstOrFail(); // 144 store, 96 dispensary
    $officer = reqUser(['inventory-officer']);
    $pharmacist = reqUser(['pharmacy-staff']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 50]], $pharmacist);

    actingAs($officer)->post(route('inventory.requisitions.approve', $requisition));
    actingAs($officer)->post(route('inventory.requisitions.issue', $requisition));

    actingAs($pharmacist)->post(route('inventory.requisitions.receive', $requisition), [
        'quantities' => [$requisition->lines->first()->id => 45],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $requisition->refresh();
    expect($requisition->status)->toBe(RequisitionStatus::Received)
        ->and($requisition->lines()->first()->quantity_received)->toBe(45)
        ->and($item->quantityAt(dispensary()))->toBe(96 + 45)
        ->and($item->fresh()->quantity_on_hand)->toBe(240 - 5);

    $writeOff = $item->movements()->where('type', StockMovementType::Adjustment->value)->firstOrFail();
    expect($writeOff->quantity_change)->toBe(-5)
        ->and($writeOff->stock_location_id)->toBe(dispensary()->id)
        ->and($writeOff->reason)->toContain($requisition->number);
});

test('the store cannot issue more than it holds', function () {
    $item = InventoryItem::where('code', 'DRG-0009')->firstOrFail(); // 36 in store
    $officer = reqUser(['inventory-officer']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 50]]);

    actingAs($officer)->post(route('inventory.requisitions.approve', $requisition), [
        'quantities' => [$requisition->lines->first()->id => 50],
    ]);
    actingAs($officer)->post(route('inventory.requisitions.issue', $requisition))
        ->assertSessionHasErrors('requisition');

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Approved)
        ->and($item->quantityAt(store()))->toBe(36);
});

test('a requisition follows its lifecycle in order', function () {
    $item = InventoryItem::where('code', 'DRG-0001')->firstOrFail();
    $officer = reqUser(['inventory-officer']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 10]]);

    // Cannot issue or receive before approval.
    actingAs($officer)->post(route('inventory.requisitions.issue', $requisition))->assertSessionHasErrors('requisition');
    actingAs($officer)->post(route('inventory.requisitions.receive', $requisition))->assertSessionHasErrors('requisition');

    actingAs($officer)->post(route('inventory.requisitions.approve', $requisition));
    actingAs($officer)->post(route('inventory.requisitions.issue', $requisition));

    // Once issued it can no longer be cancelled.
    actingAs($officer)->post(route('inventory.requisitions.cancel', $requisition), ['reason' => 'Too late'])
        ->assertSessionHasErrors('requisition');
    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Issued);
});

test('a requisition can be cancelled while it is still a request', function () {
    $item = InventoryItem::where('code', 'DRG-0001')->firstOrFail();
    $pharmacist = reqUser(['pharmacy-staff']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 10]], $pharmacist);

    actingAs($pharmacist)->post(route('inventory.requisitions.cancel', $requisition), ['reason' => 'Raised in error'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($requisition->fresh()->status)->toBe(RequisitionStatus::Cancelled)
        ->and($requisition->fresh()->cancel_reason)->toBe('Raised in error')
        ->and($item->quantityAt(store()))->toBe(300);
});

test('approving and issuing are store officer actions', function () {
    $item = InventoryItem::where('code', 'DRG-0001')->firstOrFail();
    $pharmacist = reqUser(['pharmacy-staff']);
    $requisition = raise([['inventory_item_id' => $item->id, 'quantity' => 10]], $pharmacist);

    actingAs($pharmacist)->post(route('inventory.requisitions.approve', $requisition))->assertForbidden();
    actingAs($pharmacist)->post(route('inventory.requisitions.issue', $requisition))->assertForbidden();
});

test('stock can be received straight into the dispensary', function () {
    $item = InventoryItem::factory()->create(['quantity_on_hand' => 0]);

    actingAs(reqUser(['inventory-officer']))->post(route('inventory.receive', $item), [
        'quantity' => 40,
        'stock_location_id' => dispensary()->id,
    ])->assertRedirect();

    expect($item->quantityAt(dispensary()))->toBe(40)
        ->and($item->quantityAt(store()))->toBe(0);

    actingAs(reqUser(['inventory-officer']))->get(route('inventory.index', ['location' => StockLocation::DISPENSARY]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.location', StockLocation::DISPENSARY)
            ->where('items', fn ($items) => collect($items)->firstWhere('id', $item->id)['at_location'] === 40)
        );
});

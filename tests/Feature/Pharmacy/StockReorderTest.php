<?php

use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockLocations;
use App\Services\StockReorder;
use App\Support\ReorderSuggestion;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Database\Seeders\StockLocationsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(StockLocationsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function reorderUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * An item with stock at the main store and a record of use.
 */
function consumingItem(int $received, int $issued): InventoryItem
{
    $item = InventoryItem::factory()->create(['quantity_on_hand' => 0, 'reorder_level' => 5]);
    $inventory = app(InventoryService::class);
    $actor = reorderUser(['inventory-officer']);
    $store = app(StockLocations::class)->receiving();

    $inventory->receiveStock($item, $received, ['batch_number' => 'A'], $actor, $store);

    if ($issued > 0) {
        $inventory->issue($item, $issued, $actor, from: $store);
    }

    return $item->fresh();
}

test('average monthly consumption counts issues but not transfers or corrections', function () {
    $item = consumingItem(received: 500, issued: 90);
    $inventory = app(InventoryService::class);
    $actor = reorderUser(['inventory-officer']);
    $store = app(StockLocations::class)->receiving();
    $dispensary = StockLocation::where('slug', StockLocation::DISPENSARY)->firstOrFail();

    $inventory->transfer($item, 50, $store, $dispensary, $actor);
    $inventory->adjustBatch($item->batches()->where('stock_location_id', $store->id)->firstOrFail(), -5, 'Damaged', $actor);

    $reorder = app(StockReorder::class);
    expect($reorder->consumption()->get($item->id))->toBe(90)
        ->and($reorder->averageMonthlyConsumption()->get($item->id))->toBe(30.0);

    $suggestion = $reorder->suggestions()->first(fn (ReorderSuggestion $s) => $s->item->is($item));
    // 500 received − 90 issued − 5 damaged = 405 on hand; 13.5 months of cover.
    expect($suggestion->onHand)->toBe(405)
        ->and($suggestion->monthsOfCover)->toBe(13.5)
        // Reorder at lead time (14 days ≈ 0.47 mo) + 1 month cover: ceil(30 × 1.467) = 44.
        ->and($suggestion->suggestedReorderLevel)->toBe(44)
        // Target 3 months = 90; already above it.
        ->and($suggestion->suggestedOrderQuantity)->toBe(0)
        ->and($suggestion->needsOrder)->toBeFalse();
});

test('an item below its suggested level is flagged with an order quantity', function () {
    $item = consumingItem(received: 100, issued: 90); // 10 left, 30 a month

    $suggestion = app(StockReorder::class)->suggestions()->first(fn (ReorderSuggestion $s) => $s->item->is($item));
    expect($suggestion->needsOrder)->toBeTrue()
        ->and($suggestion->suggestedOrderQuantity)->toBe(80); // 3 × 30 − 10

    // Items needing an order are listed first.
    $quiet = InventoryItem::factory()->create(['quantity_on_hand' => 0]);
    $positions = array_flip(app(StockReorder::class)->suggestions()->map(fn (ReorderSuggestion $s) => $s->item->id)->all());
    expect($positions[$item->id])->toBeLessThan($positions[$quiet->id]);
});

test('suggested reorder levels can be applied to consuming items', function () {
    $item = consumingItem(received: 500, issued: 90);
    $quiet = InventoryItem::factory()->create(['quantity_on_hand' => 0, 'reorder_level' => 7]);

    actingAs(reorderUser(['inventory-officer']))->post(route('inventory.reorder.apply'))
        ->assertRedirect();

    expect($item->fresh()->reorder_level)->toBe(44)
        ->and($quiet->fresh()->reorder_level)->toBe(7);
});

test('reorder settings change the suggestions and are limited to store officers', function () {
    $item = consumingItem(received: 500, issued: 90);
    $officer = reorderUser(['inventory-officer']);

    actingAs(reorderUser(['pharmacy-staff']))->patch(route('inventory.settings.update'), [
        'lead_time_days' => 30, 'min_cover_months' => 2, 'max_cover_months' => 6, 'consumption_window_days' => 90,
    ])->assertForbidden();

    actingAs($officer)->patch(route('inventory.settings.update'), [
        'lead_time_days' => 30, 'min_cover_months' => 2, 'max_cover_months' => 6, 'consumption_window_days' => 90,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $reorder = app(StockReorder::class);
    expect($reorder->settings()['lead_time_days'])->toBe(30);

    $suggestion = $reorder->suggestions()->first(fn (ReorderSuggestion $s) => $s->item->is($item));
    // ceil(30 × (1 + 2)) = 90; target 6 × 30 = 180; on hand 410 so nothing to order.
    expect($suggestion->suggestedReorderLevel)->toBe(90)
        ->and($suggestion->suggestedOrderQuantity)->toBe(0);

    actingAs($officer)->patch(route('inventory.settings.update'), [
        'lead_time_days' => 30, 'min_cover_months' => 4, 'max_cover_months' => 2, 'consumption_window_days' => 90,
    ])->assertSessionHasErrors('max_cover_months');

    actingAs($officer)->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->where('reorder.lead_time_days', 30)->where('can.manage_settings', true));
});

test('the new inventory and pharmacy reports run', function () {
    consumingItem(received: 100, issued: 90);
    $cmd = reorderUser(['chief-medical-director']);

    actingAs($cmd)->get(route('reports.run', 'reorder-suggestions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.action', 'Order now'));

    foreach (['requisition-register', 'prescription-fulfilment', 'stock-by-location'] as $key) {
        actingAs($cmd)->get(route('reports.run', $key))->assertOk();
    }
});

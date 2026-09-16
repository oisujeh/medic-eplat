<?php

use App\Enums\FundingSource;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Database\Seeders\StockLocationsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(StockLocationsSeeder::class);
});

function storeOfficer(): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::where('slug', 'inventory-officer')->pluck('id'));

    return $user->fresh();
}

test('a received batch is tagged with who paid for it', function () {
    $item = InventoryItem::factory()->create();

    actingAs(storeOfficer())
        ->post(route('inventory.receive', $item), ['quantity' => 50, 'funding_source' => 'pepfar'])
        ->assertSessionHasNoErrors();

    expect($item->batches()->first()->funding_source)->toBe(FundingSource::Pepfar);

    actingAs(storeOfficer())
        ->post(route('inventory.receive', $item), ['quantity' => 5, 'funding_source' => 'lottery'])
        ->assertSessionHasErrors('funding_source');
});

test('a batch falls back to the item\'s usual funding source', function () {
    $item = InventoryItem::factory()->create(['funding_source' => FundingSource::DrugRevolvingFund]);

    $batch = app(InventoryService::class)->receiveStock($item, 20, [], User::factory()->create());

    expect($batch->funding_source)->toBe(FundingSource::DrugRevolvingFund)
        ->and(FundingSource::Pepfar->isProgramme())->toBeTrue()
        ->and(FundingSource::DrugRevolvingFund->isProgramme())->toBeFalse();
});

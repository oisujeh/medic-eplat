<?php

use App\Models\Payer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PayersSeeder;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function payerAdmin(array $roleSlugs = ['cashier']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('payers are listed with their footprint', function () {
    $this->seed(PayersSeeder::class);

    actingAs(payerAdmin())
        ->get(route('claims.payers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('claims/Payers')
            ->has('payers', 6)
            ->where('payers.0.code', 'AVON')
        );
});

test('a payer can be registered with its tariff rules', function () {
    actingAs(payerAdmin())
        ->post(route('claims.payers.store'), [
            'name' => 'Sterling Health HMO',
            'code' => 'sterling',
            'type' => 'hmo',
            'discount_percent' => 15,
            'drug_copay_percent' => 0,
            'contact_person' => 'Claims desk',
            'email' => 'claims@sterlinghmo.example',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payer = Payer::firstOrFail();

    expect($payer->code)->toBe('STERLING')
        ->and($payer->type->value)->toBe('hmo')
        ->and($payer->discount_percent)->toBe(15.0)
        ->and($payer->is_active)->toBeTrue();
});

test('payer codes must be unique', function () {
    Payer::factory()->create(['code' => 'NHIA']);

    actingAs(payerAdmin())
        ->post(route('claims.payers.store'), ['name' => 'Another', 'code' => 'nhia', 'type' => 'nhia'])
        ->assertSessionHasErrors('code');
});

test('a payer can be updated and deactivated', function () {
    $payer = Payer::factory()->create();

    actingAs(payerAdmin())
        ->patch(route('claims.payers.update', $payer), [
            'name' => 'Renamed HMO',
            'code' => $payer->code,
            'type' => 'corporate',
            'discount_percent' => 5,
            'drug_copay_percent' => 20,
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payer->refresh();

    expect($payer->name)->toBe('Renamed HMO')
        ->and($payer->type->value)->toBe('corporate')
        ->and($payer->drug_copay_percent)->toBe(20.0)
        ->and($payer->is_active)->toBeFalse();
});

test('the payers seeder is idempotent', function () {
    $this->seed(PayersSeeder::class);
    $this->seed(PayersSeeder::class);

    expect(Payer::count())->toBe(6)
        ->and(Payer::where('code', 'NHIA')->firstOrFail()->drug_copay_percent)->toBe(10.0);
});

test('staff outside the module cannot manage payers', function () {
    actingAs(payerAdmin(['nurse']))
        ->post(route('claims.payers.store'), ['name' => 'X', 'code' => 'X', 'type' => 'hmo'])
        ->assertForbidden();
});

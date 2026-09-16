<?php

use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\BillCharge;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Role;
use App\Models\User;
use App\Services\BillingService;
use App\Services\FacilitySettings;
use Database\Seeders\RolesAndModulesSeeder;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

function openBill(?Payer $payer = null): Bill
{
    $patient = Patient::factory()->create($payer ? ['coverage' => 'hmo', 'payer_id' => $payer->id] : []);

    return app(BillingService::class)->openBillFor($patient, null);
}

test('a charge posts while billing is on', function () {
    $charge = app(BillingService::class)->postCharge(openBill(), BillCharge::SOURCE_CONSULTATION, 'Consultation', 1, 2500, User::factory()->create());

    expect($charge)->toBeInstanceOf(BillCharge::class)
        ->and((float) $charge->total)->toBe(2500.0)
        ->and($charge->payer_id)->toBeNull();
});

test('nothing posts once the facility switches billing off', function () {
    app(FacilitySettings::class)->save([...TestCase::TEST_FACILITY, 'billing_enabled' => false]);
    $bill = openBill();

    $charge = app(BillingService::class)->postCharge($bill, BillCharge::SOURCE_CONSULTATION, 'Consultation', 1, 2500, User::factory()->create());

    expect($charge)->toBeNull()
        ->and($bill->charges()->count())->toBe(0)
        ->and(app(BillingService::class)->enabled())->toBeFalse();
});

test('a charge names the patient\'s payer so a visit can carry more than one', function () {
    $payer = Payer::factory()->create();
    $bill = openBill($payer);
    $billing = app(BillingService::class);
    $actor = User::factory()->create();

    $covered = $billing->postCharge($bill, BillCharge::SOURCE_LABORATORY, 'FBC', 1, 1500, $actor);
    $selfPaid = $billing->postCharge($bill, BillCharge::SOURCE_PHARMACY, 'Vitamin C', 1, 200, $actor, payerId: null);
    $other = $billing->postCharge($bill, BillCharge::SOURCE_PHARMACY, 'Dressing', 1, 800, $actor, payerId: Payer::factory()->create()->id);

    expect($covered->payer_id)->toBe($payer->id)
        ->and($selfPaid->payer_id)->toBe($payer->id)
        ->and($other->payer_id)->not->toBe($payer->id)
        ->and($bill->charges()->distinct('payer_id')->count('payer_id'))->toBe(2);
});

test('switching billing off takes the billing and claims modules away from everyone', function () {
    $this->seed(RolesAndModulesSeeder::class);
    $cashier = User::factory()->create();
    $cashier->roles()->sync(Role::where('slug', 'cashier')->pluck('id'));
    $admin = User::factory()->create();
    $admin->roles()->sync(Role::where('slug', 'super-administrator')->pluck('id'));

    actingAs($cashier->fresh())->get(route('billing.index'))->assertOk();

    app(FacilitySettings::class)->save([...TestCase::TEST_FACILITY, 'billing_enabled' => false]);

    actingAs($cashier->fresh())->get(route('billing.index'))->assertForbidden();
    actingAs($cashier->fresh())->get(route('claims.index'))->assertForbidden();
    actingAs($admin->fresh())->get(route('billing.index'))->assertForbidden();

    $slugs = $admin->fresh()->accessibleModules()->pluck('slug')->all();

    expect($slugs)->not->toContain('billing')
        ->and($slugs)->not->toContain('claims')
        ->and($slugs)->toContain('pharmacy');

    actingAs($admin->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('facility.billing_enabled', false));
});

test('every payment is issued a receipt number from the facility series', function () {
    $bill = openBill();
    $billing = app(BillingService::class);
    $billing->postCharge($bill, BillCharge::SOURCE_CONSULTATION, 'Consultation', 1, 2500, User::factory()->create());

    $first = $billing->recordPayment($bill, 1000, PaymentMethod::Cash, null, User::factory()->create());
    $second = $billing->recordPayment($bill, 1500, PaymentMethod::Pos, 'POS-9981', User::factory()->create());

    expect($first->receipt_number)->toBe(sprintf('RCP/%d/000001', now()->year))
        ->and($second->receipt_number)->toBe(sprintf('RCP/%d/000002', now()->year));
});

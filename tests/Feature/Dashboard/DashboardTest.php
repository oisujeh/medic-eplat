<?php

use App\Models\Bill;
use App\Models\InventoryItem;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\FacilitySettings;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function dashUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Put a patient on a service point's queue the way the front desk does.
 */
function dashQueue(string $servicePointSlug, string $priority = 'normal'): Patient
{
    $patient = Patient::factory()->create();

    actingAs(dashUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', $servicePointSlug)->firstOrFail()->id,
        'priority' => $priority,
    ]);

    return $patient;
}

/**
 * The keys of the tiles on the rendered page.
 *
 * @param  array<int, array<string, mixed>>  $tiles
 * @return array<int, string>
 */
function tileKeys(array $tiles): array
{
    return array_column($tiles, 'key');
}

test('a records officer lands on today\'s registrations and the queues, not on money', function () {
    Patient::factory()->count(2)->create();

    actingAs(dashUser(['records-officer']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('today')
            ->where('home.tiles', fn ($tiles) => in_array('registrations_today', tileKeys($tiles->all()), true)
                && in_array('waiting', tileKeys($tiles->all()), true)
                && collect($tiles)->where('format', 'money')->isEmpty())
            ->where('home.tiles', fn ($tiles) => collect($tiles)->firstWhere('key', 'registrations_today')['value'] === 2)
            ->whereNot('home.sections.queues', null)
            ->where('home.sections.billing', null)
            ->where('home.sections.clinical', null)
            ->where('home.sections.management', null)
        );
});

test('a physician sees the patients waiting for consultation, emergencies first', function () {
    $routine = dashQueue('consultation');
    $emergency = dashQueue('consultation', 'emergency');
    dashQueue('triage');

    actingAs(dashUser(['physician']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('home.tiles', fn ($tiles) => collect($tiles)->firstWhere('key', 'my_waiting')['value'] === 2)
            ->has('home.sections.clinical.worklist', 2)
            ->where('home.sections.clinical.worklist.0.patient', $emergency->fullName())
            ->where('home.sections.clinical.worklist.0.priority', 'emergency')
            ->where('home.sections.clinical.worklist.1.patient', $routine->fullName())
            ->where('home.sections.clinical.waiting_count', 2)
        );
});

test('a nurse sees the patients waiting at nursing points', function () {
    dashQueue('triage');
    dashQueue('consultation');

    actingAs(dashUser(['nurse']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('home.sections.nursing.worklist', 1)
            ->where('home.sections.clinical', null)
        );
});

test('a cashier sees today\'s takings and the bills still owing', function () {
    $bill = Bill::factory()->create();
    Payment::create(['bill_id' => $bill->id, 'amount' => 4200, 'method' => 'cash']);

    actingAs(dashUser(['cashier']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('home.tiles', fn ($tiles) => (float) collect($tiles)->firstWhere('key', 'collected_today')['value'] === 4200.0)
            ->where('home.sections.billing.collected_today', 4200)
            ->where('home.sections.billing.unpaid_count', 1)
            ->where('home.sections.billing.unpaid.0.id', $bill->id)
            ->where('home.sections.billing.by_method.0.value', 4200)
        );
});

test('a laboratory scientist sees the specimen worklist', function () {
    $order = LabOrder::factory()->create();

    actingAs(dashUser(['laboratory-staff']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('home.tiles', fn ($tiles) => collect($tiles)->firstWhere('key', 'lab_awaiting_collection')['value'] === 1)
            ->has('home.sections.laboratory.worklist', 1)
            ->where('home.sections.laboratory.worklist.0.accession_number', $order->accession_number)
            ->where('home.sections.laboratory.active_count', 1)
        );
});

test('management gets the facility picture first and a way into the executive overview', function () {
    actingAs(dashUser(['chief-medical-director']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('home.tiles.0.key', 'waiting')
            ->where('home.tiles', fn ($tiles) => in_array('collected_today', tileKeys($tiles->all()), true))
            ->where('home.sections.management.overview_href', '/reports/overview')
            ->has('home.sections.management.revenue')
        );
});

test('alerts only list conditions that need action, for modules the user can reach', function () {
    InventoryItem::factory()->create(['quantity_on_hand' => 2, 'reorder_level' => 20, 'is_active' => true]);

    actingAs(dashUser(['inventory-officer']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('home.alerts', fn ($alerts) => collect($alerts)->firstWhere('key', 'low_stock')['count'] === 1)
        );

    actingAs(dashUser(['records-officer']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('home.alerts', []));
});

test('the facility notice reaches every home screen', function () {
    app(FacilitySettings::class)->save([...TestCase::TEST_FACILITY, 'notice' => 'Grand round moves to Friday this week.']);

    actingAs(dashUser(['nurse']))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('facility.notice', 'Grand round moves to Friday this week.'));
});

test('a user with no roles still gets a home screen', function () {
    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('home.tiles', [])
            ->where('home.alerts', [])
        );
});

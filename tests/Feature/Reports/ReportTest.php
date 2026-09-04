<?php

use App\Models\Bill;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function reportUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Record a payment against a fresh bill at a given time.
 */
function recordPayment(float $amount, string $when = 'now'): Payment
{
    $bill = Bill::factory()->create();
    $payment = Payment::create(['bill_id' => $bill->id, 'amount' => $amount, 'method' => 'cash']);
    Payment::where('id', $payment->id)->update(['created_at' => Carbon::parse($when)]);

    return $payment->fresh();
}

// ---------------------------------------------------------------- Catalogue

test('the chief medical director can open the report catalogue', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Index')
            ->has('categories')
            ->has('reports')
            ->has('featured')
            ->has('presets')
        );
});

test('staff without the reports module are forbidden from the catalogue', function () {
    actingAs(reportUser(['cashier']))->get(route('reports.index'))->assertForbidden();
    actingAs(reportUser(['records-officer']))->get(route('reports.index'))->assertForbidden();
});

test('the catalogue reports carry a runnable url', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('reports', fn ($reports) => collect($reports)->firstWhere('key', 'revenue-summary')['url'] !== null
                && collect($reports)->firstWhere('key', 'executive-overview')['url'] === '/reports/overview')
        );
});

// ------------------------------------------------------------------ Overview

test('the executive overview renders its analytics payload', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Overview')
            ->has('report.kpis.revenue')
            ->has('report.revenueTrend.points')
        );
});

// -------------------------------------------------------------------- Runner

test('a table report runs and returns columns, rows and a summary', function () {
    recordPayment(5000, 'now');

    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.run', 'revenue-summary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Report')
            ->where('report.key', 'revenue-summary')
            ->has('columns')
            ->has('rows', 1)
            ->where('summary', fn ($summary) => collect($summary)
                ->contains(fn ($s) => str_contains($s['value'], '5,000')))
        );
});

test('a table report respects the selected date range', function () {
    recordPayment(5000, 'now');

    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.run', ['report' => 'revenue-summary', 'range' => 'custom', 'from' => '2000-01-01', 'to' => '2000-01-31']))
        ->assertInertia(fn ($page) => $page->has('rows', 0));
});

test('the low stock report lists items below reorder level', function () {
    InventoryItem::factory()->create(['name' => 'Amoxicillin', 'quantity_on_hand' => 4, 'reorder_level' => 30, 'is_active' => true]);

    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.run', 'low-stock'))
        ->assertInertia(fn ($page) => $page
            ->where('rows', fn ($rows) => collect($rows)->pluck('name')->contains('Amoxicillin'))
        );
});

test('running a dashboard-type report redirects to its screen', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.run', 'executive-overview'))
        ->assertRedirect('/reports/overview');
});

test('an unknown report key is not found', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.run', 'does-not-exist'))
        ->assertNotFound();
});

test('staff without the reports module cannot run a report', function () {
    actingAs(reportUser(['cashier']))
        ->get(route('reports.run', 'revenue-summary'))
        ->assertForbidden();
});

// -------------------------------------------------------------------- Export

test('a report exports as a csv download', function () {
    recordPayment(5000, 'now');

    $response = actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.export', 'revenue-summary'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Amount')->toContain('5,000');
});

test('an unknown report cannot be exported', function () {
    actingAs(reportUser(['chief-medical-director']))
        ->get(route('reports.export', 'nope'))
        ->assertNotFound();
});

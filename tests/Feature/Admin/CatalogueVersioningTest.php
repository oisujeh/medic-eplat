<?php

use App\Models\BillCharge;
use App\Models\CatalogueVersion;
use App\Models\LabTest;
use App\Models\NotifiableDisease;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\BillingService;
use App\Services\LabWorkflowService;
use Database\Seeders\IcdCodesSeeder;
use Database\Seeders\NotifiableDiseasesSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function versionUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

// ---------------------------------------------------------- The mechanism

test('a catalogue entry starts at version 1 and each meaningful change opens the next', function () {
    $charge = ServiceCharge::factory()->create(['name' => 'Dressing', 'price' => 1500, 'sort_order' => 1]);

    expect($charge->currentVersion()->version)->toBe(1)
        ->and((float) $charge->currentVersion()->snapshot['price'])->toBe(1500.0)
        ->and($charge->currentVersion()->effective_to)->toBeNull();

    // Housekeeping is not a new version.
    $charge->update(['sort_order' => 5, 'is_active' => true]);
    expect($charge->versions()->count())->toBe(1);

    $this->travelTo(now()->addDays(10));
    app(AuditTrail::class)->withReason('Annual tariff review', fn () => $charge->update(['price' => 2000]));

    $versions = $charge->versions()->reorder('version')->get();

    expect($versions)->toHaveCount(2)
        ->and($versions[0]->effective_to?->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($versions[1]->version)->toBe(2)
        ->and($versions[1]->effective_from->toDateString())->toBe(now()->toDateString())
        ->and((float) $versions[1]->snapshot['price'])->toBe(2000.0)
        ->and($versions[1]->reason)->toBe('Annual tariff review')
        ->and($charge->versionAt(now()->subDays(3))?->version)->toBe(1)
        ->and($charge->versionAt(now())?->version)->toBe(2)
        ->and($charge->versionAt(now()->subDays(30)))->toBeNull();
});

test('an entry created with model events off still gets its first version on demand', function () {
    $test = LabTest::withoutEvents(fn () => LabTest::factory()->create(['reference_high' => 10]));

    expect(CatalogueVersion::count())->toBe(0)
        ->and($test->currentVersion()->version)->toBe(1)
        ->and(CatalogueVersion::count())->toBe(1);
});

test('a version is immutable except for its closing date', function () {
    $version = ServiceCharge::factory()->create()->currentVersion();

    expect(fn () => $version->update(['snapshot' => ['price' => '1.00']]))->toThrow(LogicException::class)
        ->and(fn () => $version->delete())->toThrow(LogicException::class);
});

// ----------------------------------------------------- Records keep theirs

test('a surveillance case keeps the disease version it was detected under', function () {
    $this->seed(IcdCodesSeeder::class);
    $this->seed(NotifiableDiseasesSeeder::class);

    $physician = versionUser(['physician']);
    $patient = Patient::factory()->create();
    actingAs(versionUser(['records-officer']))->post(route('patients.route', $patient), ['service_point_id' => ServicePoint::where('slug', 'consultation')->value('id'), 'priority' => 'normal']);
    $encounter = openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);

    actingAs($physician)->post(route('encounters.problems.store', $encounter), ['name' => 'Cholera', 'code' => 'A00', 'status' => 'active', 'role' => 'primary']);

    $case = SurveillanceCase::firstOrFail();
    $cholera = NotifiableDisease::where('slug', 'cholera')->firstOrFail();

    expect($case->catalogue_version_id)->toBe($cholera->currentVersion()->id)
        ->and($case->version->version)->toBe(1);

    // The catalogue tightens the definition tomorrow; the old case is untouched.
    $this->travelTo(now()->addDay());
    $cholera->update(['case_definition' => 'Revised definition', 'notification_hours' => 12]);

    $case->refresh();
    expect($case->case_definition)->not->toBe('Revised definition')
        ->and($case->version->version)->toBe(1)
        ->and($case->version->snapshot['notification_hours'])->toBe(24);

    // A new case is detected under version 2.
    $second = Patient::factory()->create();
    actingAs(versionUser(['records-officer']))->post(route('patients.route', $second), ['service_point_id' => ServicePoint::where('slug', 'consultation')->value('id'), 'priority' => 'normal']);
    $next = openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);
    actingAs($physician)->post(route('encounters.problems.store', $next), ['name' => 'Cholera', 'code' => 'A00', 'status' => 'active', 'role' => 'primary']);

    expect(SurveillanceCase::latest('id')->first()->version->version)->toBe(2);
});

test('a lab result keeps the test version and reference range it was ordered under', function () {
    $test = LabTest::factory()->create(['name' => 'Haemoglobin', 'reference_low' => 12, 'reference_high' => 16, 'price' => 0]);
    $physician = versionUser(['physician']);
    $patient = Patient::factory()->create();

    $order = app(LabWorkflowService::class)->createOrder($patient, $physician, collect([$test]));
    $result = $order->results()->firstOrFail();

    expect($result->catalogue_version_id)->toBe($test->currentVersion()->id)
        ->and($result->reference_high)->toBe(16.0);

    $test->update(['reference_high' => 17.5]);

    expect($result->fresh()->reference_high)->toBe(16.0)
        ->and($result->fresh()->version->version)->toBe(1)
        ->and(app(LabWorkflowService::class)->createOrder($patient, $physician, collect([$test->fresh()]))->results()->first()->version->version)->toBe(2);
});

test('a fee-schedule charge keeps the tariff version it was priced from', function () {
    $charge = ServiceCharge::factory()->create(['price' => 1500]);
    $cashier = versionUser(['cashier']);
    $patient = Patient::factory()->create();
    $billing = app(BillingService::class);
    $bill = $billing->openBillFor($patient, null);

    $posted = $billing->postCharge($bill, 'procedure', $charge->name, 1, (float) $charge->price, $cashier, charge: $charge);
    $charge->update(['price' => 2500]);
    $later = $billing->postCharge($bill, 'procedure', $charge->name, 1, (float) $charge->fresh()->price, $cashier, charge: $charge->fresh());

    expect($posted->version->version)->toBe(1)
        ->and((float) $posted->unit_price)->toBe(1500.0)
        ->and($later->version->version)->toBe(2)
        ->and((float) $later->unit_price)->toBe(2500.0)
        ->and(BillCharge::whereNull('catalogue_version_id')->count())->toBe(0);
});

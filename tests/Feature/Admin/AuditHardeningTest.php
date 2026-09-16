<?php

use App\Models\AuditLog;
use App\Models\IcdCode;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\AuditTrail;
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
function hardeningUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

test('configuration records are audited', function () {
    actingAs(hardeningUser(['super-administrator']));

    $test = LabTest::factory()->create(['reference_high' => 10]);
    $test->update(['reference_high' => 12]);

    ServicePoint::where('slug', 'triage')->firstOrFail()->update(['is_active' => false]);

    expect(AuditLog::query()->where('auditable_type', LabTest::class)->where('action', 'updated')
        ->where('new_values->reference_high', 12)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_type', ServicePoint::class)->where('action', 'updated')->exists())->toBeTrue();
});

test('closing a visit audits every queue entry it cancels', function () {
    $records = hardeningUser(['records-officer']);
    $patient = Patient::factory()->create();

    foreach (['triage', 'consultation'] as $slug) {
        actingAs($records)->post(route('patients.route', $patient), [
            'service_point_id' => ServicePoint::where('slug', $slug)->firstOrFail()->id,
            'priority' => 'normal',
        ]);
    }

    $visit = $patient->openVisit();
    actingAs($records)->post(route('visits.close', $visit))->assertRedirect();

    $cancelled = AuditLog::query()
        ->where('auditable_type', QueueEntry::class)
        ->where('action', 'updated')
        ->where('new_values->status', 'cancelled')
        ->count();

    expect($cancelled)->toBe(2);
});

test('a reason given for an action is written to the trail', function () {
    $records = hardeningUser(['records-officer']);
    $patient = Patient::factory()->create();

    actingAs($records)->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'triage')->firstOrFail()->id,
        'priority' => 'normal',
    ]);
    $entry = QueueEntry::latest('id')->firstOrFail();

    actingAs(hardeningUser(['nurse']))
        ->post(route('queue-entries.cancel', $entry), ['reason' => 'Patient left before triage'])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('auditable_type', QueueEntry::class)
        ->where('auditable_id', $entry->id)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($log->reason)->toBe('Patient left before triage')
        ->and(app(AuditTrail::class)->verify()['intact'])->toBeTrue();
});

test('the ICD import writes one summary entry instead of one per code', function () {
    $file = tempnam(sys_get_temp_dir(), 'icd');
    file_put_contents($file, "A00,Cholera,Certain infectious diseases\nA01,Typhoid fever,Certain infectious diseases\n");

    $this->artisan('icd:import', ['path' => $file])->assertSuccessful();

    expect(IcdCode::count())->toBe(2)
        ->and(AuditLog::query()->where('auditable_type', IcdCode::class)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', AuditLog::ACTION_IMPORTED)->where('label', 'like', 'ICD-10 import%')->exists())->toBeTrue();

    unlink($file);
});

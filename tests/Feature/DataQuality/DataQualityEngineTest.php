<?php

use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualitySeverity;
use App\Enums\EncounterStatus;
use App\Events\PatientRegistered;
use App\Events\PatientUpdated;
use App\Models\AuditLog;
use App\Models\DataQualityIssue;
use App\Models\DataQualityRule;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\DataQuality;
use App\Services\DomainEvents;
use App\Support\DataQuality\DataQualityChecks;
use Database\Seeders\DataQualityRulesSeeder;
use Database\Seeders\RolesAndModulesSeeder;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(DataQualityRulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function dqUser(array $roleSlugs = ['records-officer']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A folder with nobody to call and nowhere to write to.
 */
function unreachablePatient(): Patient
{
    return Patient::factory()->create(['phone' => null, 'address' => null]);
}

function rule(string $key): DataQualityRule
{
    return DataQualityRule::where('key', $key)->firstOrFail();
}

// ------------------------------------------------------------- The registry

test('the seeder writes a rule for every check the code carries', function () {
    expect(DataQualityRule::count())->toBe(count(DataQualityChecks::checks()))
        ->and(rule('encounter_unsigned')->parameters)->toBe(['hours' => 24])
        ->and(rule('encounter_unsigned')->severity)->toBe(DataQualitySeverity::Major);
});

test('reseeding leaves a facility\'s own tuning alone', function () {
    rule('encounter_unsigned')->update(['severity' => DataQualitySeverity::Critical, 'parameters' => ['hours' => 4], 'is_active' => false]);

    $this->seed(DataQualityRulesSeeder::class);

    $rule = rule('encounter_unsigned');

    expect($rule->severity)->toBe(DataQualitySeverity::Critical)
        ->and($rule->parameters)->toBe(['hours' => 4])
        ->and($rule->is_active)->toBeFalse();
});

// ------------------------------------------------------- Judging on a write

test('a folder with no way to reach the patient raises an issue as it is written', function () {
    $patient = unreachablePatient();

    app(DomainEvents::class)->publish(new PatientRegistered($patient, dqUser()));

    $issue = DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail();

    expect($issue->rule->key)->toBe('patient_contact_missing')
        ->and($issue->status)->toBe(DataQualityIssueStatus::Open)
        ->and($issue->patient_id)->toBe($patient->id)
        ->and($issue->severity)->toBe(DataQualitySeverity::Major)
        ->and($issue->owner_role)->toBe('records-officer')
        ->and($issue->summary)->toContain('No phone number and no address')
        // The rule version it was raised under is kept with it.
        ->and($issue->catalogue_version_id)->toBe(rule('patient_contact_missing')->currentVersion()->id);
});

test('putting the folder right closes the issue in the same request', function () {
    $patient = unreachablePatient();
    $actor = dqUser();

    app(DomainEvents::class)->publish(new PatientRegistered($patient, $actor));

    $patient->update(['phone' => '08031234567']);
    app(DomainEvents::class)->publish(new PatientUpdated($patient, ['phone'], $actor));

    $issue = DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail();

    expect($issue->status)->toBe(DataQualityIssueStatus::Resolved)
        ->and($issue->resolved_at)->not->toBeNull()
        ->and($issue->resolved_by)->toBeNull()
        ->and($issue->resolution)->toBe('The record passes the rule.');
});

test('a record that goes wrong again reopens the issue it first raised', function () {
    $patient = unreachablePatient();
    $actor = dqUser();
    $events = app(DomainEvents::class);

    $events->publish(new PatientRegistered($patient, $actor));
    $first = DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail()->first_detected_at;

    $patient->update(['phone' => '08031234567']);
    $events->publish(new PatientUpdated($patient, ['phone'], $actor));

    $patient->update(['phone' => null]);
    $events->publish(new PatientUpdated($patient, ['phone'], $actor));

    $issues = DataQualityIssue::query()->where('subject_id', $patient->id)->get();

    expect($issues)->toHaveCount(1)
        ->and($issues->first()->status)->toBe(DataQualityIssueStatus::Open)
        ->and($issues->first()->first_detected_at->eq($first))->toBeTrue()
        ->and($issues->first()->resolved_at)->toBeNull();
});

test('a rule switched off raises nothing', function () {
    rule('patient_contact_missing')->update(['is_active' => false]);

    app(DomainEvents::class)->publish(new PatientRegistered(unreachablePatient(), dqUser()));

    expect(DataQualityIssue::count())->toBe(0);
});

test('merging a duplicate closes what was open against it', function () {
    $duplicate = unreachablePatient();
    $survivor = Patient::factory()->create();
    $actor = dqUser();

    app(DomainEvents::class)->publish(new PatientRegistered($duplicate, $actor));

    // Merging is not an ordinary edit: the column is guarded, and the merge
    // service is what sets it.
    $duplicate->forceFill(['merged_into_id' => $survivor->id])->save();
    app(DomainEvents::class)->publish(new PatientUpdated($duplicate, ['merged_into_id'], $actor));

    expect(DataQualityIssue::query()->where('subject_id', $duplicate->id)->firstOrFail()->status)
        ->toBe(DataQualityIssueStatus::Resolved);
});

// ------------------------------------------------------------- The sweep

test('the sweep raises the issues no write can answer', function () {
    $encounter = Encounter::factory()->create(['created_at' => now()->subDays(2), 'started_at' => now()->subDays(2)]);

    $result = app(DataQuality::class)->sweep();

    $issue = DataQualityIssue::query()->whereMorphedTo('subject', $encounter)->firstOrFail();

    expect($issue->rule->key)->toBe('encounter_unsigned')
        ->and($issue->summary)->toContain('without being signed')
        ->and($issue->owner_role)->toBe('physician')
        ->and($result['raised'])->toBe(1);
});

test('an encounter signed since the last sweep has its issue closed by the next one', function () {
    $encounter = Encounter::factory()->create(['created_at' => now()->subDays(2), 'started_at' => now()->subDays(2)]);
    $engine = app(DataQuality::class);

    $engine->sweep();

    $encounter->update(['status' => EncounterStatus::Signed, 'signed_at' => now()]);
    $result = $engine->sweep();

    expect(DataQualityIssue::query()->whereMorphedTo('subject', $encounter)->firstOrFail()->status)
        ->toBe(DataQualityIssueStatus::Resolved)
        ->and($result['resolved'])->toBe(1);
});

test('nothing is raised before a rule\'s threshold is reached', function () {
    Encounter::factory()->create(['created_at' => now()->subHours(2), 'started_at' => now()->subHours(2)]);

    app(DataQuality::class)->sweep();

    expect(DataQualityIssue::count())->toBe(0);
});

test('tightening a threshold catches records the old one let through', function () {
    Encounter::factory()->create(['created_at' => now()->subHours(6), 'started_at' => now()->subHours(6)]);
    $engine = app(DataQuality::class);

    $engine->sweep();
    expect(DataQualityIssue::count())->toBe(0);

    rule('encounter_unsigned')->update(['parameters' => ['hours' => 4]]);
    $engine->sweep();

    expect(DataQualityIssue::query()->open()->count())->toBe(1);
});

test('the sweep command reports what it did', function () {
    Encounter::factory()->create(['created_at' => now()->subDays(2), 'started_at' => now()->subDays(2)]);

    $this->artisan('data-quality:sweep')
        ->expectsOutputToContain('1 issues raised')
        ->assertSuccessful();
});

// ------------------------------------------------------------------ Waivers

test('a waived issue stays waived however often the record is judged again', function () {
    $patient = unreachablePatient();
    $actor = dqUser();
    $engine = app(DataQuality::class);

    app(DomainEvents::class)->publish(new PatientRegistered($patient, $actor));

    $issue = DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail();
    $engine->waive($issue, $actor, 'Patient has no phone; address confirmed verbally.');

    $engine->sweep();
    app(DomainEvents::class)->publish(new PatientUpdated($patient, ['surname'], $actor));

    $issue->refresh();

    expect($issue->status)->toBe(DataQualityIssueStatus::Waived)
        ->and($issue->resolution)->toBe('Patient has no phone; address confirmed verbally.')
        ->and($issue->resolved_by)->toBe($actor->id);
});

test('raising and waiving an issue is written to the audit trail', function () {
    $patient = unreachablePatient();
    $actor = dqUser();

    app(DomainEvents::class)->publish(new PatientRegistered($patient, $actor));
    $issue = DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail();

    app(DataQuality::class)->waive($issue, $actor, 'Homeless patient with no contact details.');

    expect(AuditLog::where('auditable_type', DataQualityIssue::class)->where('auditable_id', $issue->id)->count())
        ->toBe(2);
});

test('re-confirming an open issue is not an audit event', function () {
    $patient = unreachablePatient();

    app(DomainEvents::class)->publish(new PatientRegistered($patient, dqUser()));
    $before = AuditLog::count();

    app(DataQuality::class)->sweep();

    expect(AuditLog::count())->toBe($before);
});

<?php

use App\Enums\DataQualityIssueStatus;
use App\Enums\DataQualitySeverity;
use App\Events\PatientRegistered;
use App\Models\DataQualityIssue;
use App\Models\DataQualityRule;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\DataQuality;
use App\Services\DomainEvents;
use Database\Seeders\DataQualityRulesSeeder;
use Database\Seeders\RolesAndModulesSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(DataQualityRulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function queueUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * A folder nobody can be reached on, with the issue that raises.
 */
function issueOnAnUnreachableFolder(): DataQualityIssue
{
    $patient = Patient::factory()->create(['phone' => null, 'address' => null]);

    app(DomainEvents::class)->publish(new PatientRegistered($patient, queueUser(['records-officer'])));

    return DataQualityIssue::query()->where('subject_id', $patient->id)->firstOrFail();
}

// ------------------------------------------------------------------- Access

test('the queue is closed to a role without the module', function () {
    actingAs(queueUser(['pharmacy-staff']))
        ->get(route('data-quality.index'))
        ->assertForbidden();
});

test('the queue lists open issues with the screen that puts each right', function () {
    $issue = issueOnAnUnreachableFolder();

    actingAs(queueUser(['records-officer']))
        ->get(route('data-quality.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('data-quality/Issues')
            ->has('issues.data', 1)
            ->where('issues.data.0.id', $issue->id)
            ->where('issues.data.0.rule', 'No way to contact the patient')
            ->where('issues.data.0.severity', 'major')
            ->where('issues.data.0.href', route('patients.edit', $issue->patient_id))
            ->where('issues.data.0.patient.file_number', $issue->patient->file_number)
            ->where('summary.open', 1)
            ->where('can.waive', true)
            ->where('can.manage_rules', false)
        );
});

test('a user sees what their own roles are answerable for', function () {
    issueOnAnUnreachableFolder();
    Encounter::factory()->create(['created_at' => now()->subDays(2), 'started_at' => now()->subDays(2)]);
    app(DataQuality::class)->sweep();

    // Two issues, one owned by records, one by the physician.
    actingAs(queueUser(['records-officer']))
        ->get(route('data-quality.index', ['owner' => 'mine']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('issues.data', 1)
            ->where('issues.data.0.rule', 'No way to contact the patient')
            ->where('summary.open', 2)
            ->where('summary.mine', 1)
        );

    actingAs(queueUser(['physician']))
        ->get(route('data-quality.index', ['owner' => 'mine']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('issues.data', 1)
            ->where('issues.data.0.rule', 'Encounter left unsigned')
        );
});

test('the queue can be narrowed to one dimension', function () {
    issueOnAnUnreachableFolder();

    actingAs(queueUser(['records-officer']))
        ->get(route('data-quality.index', ['dimension' => 'timeliness']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('issues.data', 0));
});

// -------------------------------------------------------------- Re-checking

test('re-checking closes an issue whose record has been put right', function () {
    $issue = issueOnAnUnreachableFolder();
    $issue->patient->update(['phone' => '08031234567']);

    actingAs(queueUser(['records-officer']))
        ->post(route('data-quality.issues.recheck', $issue))
        ->assertRedirect();

    expect($issue->refresh()->status)->toBe(DataQualityIssueStatus::Resolved);
});

test('re-checking a record that still fails leaves the issue open', function () {
    $issue = issueOnAnUnreachableFolder();

    actingAs(queueUser(['records-officer']))
        ->post(route('data-quality.issues.recheck', $issue))
        ->assertRedirect();

    expect($issue->refresh()->status)->toBe(DataQualityIssueStatus::Open);
});

// ------------------------------------------------------------------ Waiving

test('waiving needs the permission', function () {
    $issue = issueOnAnUnreachableFolder();

    actingAs(queueUser(['nurse']))
        ->post(route('data-quality.issues.waive', $issue), ['reason' => 'Nothing more can be recorded.'])
        ->assertForbidden();

    expect($issue->refresh()->status)->toBe(DataQualityIssueStatus::Open);
});

test('waiving needs a reason somebody can read later', function () {
    $issue = issueOnAnUnreachableFolder();

    actingAs(queueUser(['records-officer']))
        ->post(route('data-quality.issues.waive', $issue), ['reason' => 'no'])
        ->assertSessionHasErrors('reason');

    expect($issue->refresh()->status)->toBe(DataQualityIssueStatus::Open);
});

test('a waived issue is kept with its reason and leaves the open queue', function () {
    $issue = issueOnAnUnreachableFolder();
    $officer = queueUser(['records-officer']);

    actingAs($officer)
        ->post(route('data-quality.issues.waive', $issue), ['reason' => 'Homeless patient; no contact details exist.'])
        ->assertRedirect();

    $issue->refresh();

    expect($issue->status)->toBe(DataQualityIssueStatus::Waived)
        ->and($issue->resolution)->toBe('Homeless patient; no contact details exist.')
        ->and($issue->resolved_by)->toBe($officer->id);

    actingAs($officer)
        ->get(route('data-quality.index'))
        ->assertInertia(fn ($page) => $page->has('issues.data', 0)->where('summary.waived', 1));
});

test('an issue already waived cannot be waived again', function () {
    $issue = issueOnAnUnreachableFolder();
    $officer = queueUser(['records-officer']);

    app(DataQuality::class)->waive($issue, $officer, 'Homeless patient; no contact details exist.');

    actingAs($officer)
        ->post(route('data-quality.issues.waive', $issue), ['reason' => 'Waiving a second time.'])
        ->assertStatus(422);
});

// -------------------------------------------------------------------- Rules

test('the rules screen shows how each check is set', function () {
    actingAs(queueUser(['chief-medical-director']))
        ->get(route('data-quality.rules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('data-quality/Rules')
            ->has('rules', DataQualityRule::count())
            ->where('can.manage_rules', true)
        );
});

test('retuning a rule needs the permission', function () {
    $rule = DataQualityRule::where('key', 'encounter_unsigned')->firstOrFail();

    actingAs(queueUser(['records-officer']))
        ->patch(route('data-quality.rules.update', $rule), [
            'severity' => 'critical',
            'is_active' => true,
            'parameters' => ['hours' => 4],
        ])
        ->assertForbidden();

    expect($rule->refresh()->severity)->toBe(DataQualitySeverity::Major);
});

test('retuning a rule opens a new version and leaves open issues reading as they were', function () {
    Encounter::factory()->create(['created_at' => now()->subDays(2), 'started_at' => now()->subDays(2)]);
    app(DataQuality::class)->sweep();

    $rule = DataQualityRule::where('key', 'encounter_unsigned')->firstOrFail();
    $issue = DataQualityIssue::query()->where('data_quality_rule_id', $rule->id)->firstOrFail();
    $versionAtDetection = $issue->catalogue_version_id;

    actingAs(queueUser(['chief-medical-director']))
        ->patch(route('data-quality.rules.update', $rule), [
            'severity' => 'critical',
            'owner_role' => 'nurse',
            'is_active' => true,
            'parameters' => ['hours' => 4],
        ])
        ->assertRedirect();

    $rule->refresh();
    $issue->refresh();

    expect($rule->severity)->toBe(DataQualitySeverity::Critical)
        ->and($rule->owner_role)->toBe('nurse')
        ->and($rule->parameters)->toBe(['hours' => 4])
        ->and($rule->currentVersion()->version)->toBe(2)
        // The issue still reads under the version it was raised against.
        ->and($issue->severity)->toBe(DataQualitySeverity::Major)
        ->and($issue->owner_role)->toBe('physician')
        ->and($issue->catalogue_version_id)->toBe($versionAtDetection);
});

test('switching a rule off leaves its open issues in the queue', function () {
    $issue = issueOnAnUnreachableFolder();
    $rule = $issue->rule;

    actingAs(queueUser(['chief-medical-director']))
        ->patch(route('data-quality.rules.update', $rule), [
            'severity' => $rule->severity->value,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($issue->refresh()->status)->toBe(DataQualityIssueStatus::Open);
});

// ---------------------------------------------------------------- Scorecard

test('the scorecard measures each rule against the records it judges', function () {
    issueOnAnUnreachableFolder();
    Patient::factory()->count(3)->create();

    actingAs(queueUser(['chief-medical-director']))
        ->get(route('data-quality.scorecard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('data-quality/Scorecard')
            ->where('summary.open', 1)
            ->where('summary.raised', 1)
            ->has('dimensions', 4)
            ->has('rules', DataQualityRule::count())
            ->where('rules.1.key', 'patient_contact_missing')
            ->where('rules.1.open', 1)
            ->where('rules.1.population', 4)
            ->where('rules.1.pass_rate', 75)
        );
});

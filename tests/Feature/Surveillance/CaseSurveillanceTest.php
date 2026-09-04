<?php

use App\Enums\CaseClassification;
use App\Enums\CaseNotificationStatus;
use App\Enums\NotifiableDiseaseCategory;
use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\NotifiableDisease;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Services\CaseSurveillance;
use Database\Seeders\IcdCodesSeeder;
use Database\Seeders\NotifiableDiseasesSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(IcdCodesSeeder::class);
    $this->seed(NotifiableDiseasesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function idsrUser(array $roleSlugs = ['physician']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to consultation and open the encounter as the physician.
 */
function idsrEncounter(User $physician): Encounter
{
    $patient = Patient::factory()->create();
    actingAs(idsrUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);
}

/**
 * Code a diagnosis on the encounter and return the problem it created.
 */
function codeDiagnosis(User $physician, Encounter $encounter, string $name, string $code): Problem
{
    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), [
            'name' => $name,
            'code' => $code,
            'status' => 'active',
            'role' => 'primary',
        ])
        ->assertRedirect();

    return Problem::query()->latest('id')->firstOrFail();
}

/**
 * The audit entries written against a case, oldest first.
 */
function caseAudit(SurveillanceCase $case, ?string $action = null): Collection
{
    return AuditLog::query()
        ->where('auditable_type', SurveillanceCase::class)
        ->where('auditable_id', $case->id)
        ->when($action, fn ($q) => $q->where('action', $action))
        ->orderBy('id')
        ->get();
}

// ---------------------------------------------------------------- Detection

test('coding an immediately notifiable disease opens a case with a 24-hour DSNO deadline', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);

    $problem = codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    $case = SurveillanceCase::query()->whereMorphedTo('source', $problem)->sole();

    expect($case->disease->name)->toBe('Cholera')
        ->and($case->patient_id)->toBe($encounter->patient_id)
        ->and($case->encounter_id)->toBe($encounter->id)
        ->and($case->icd_code)->toBe('A00')
        ->and($case->category)->toBe(NotifiableDiseaseCategory::Immediate)
        ->and($case->classification)->toBe(CaseClassification::Suspected)
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Pending)
        ->and($case->notification_due_at?->diffInHours($case->detected_at, true))->toBe(24.0)
        ->and($case->notificationPhase())->toBe(SurveillanceCase::PHASE_DUE)
        ->and($case->requires_contact_tracing)->toBeTrue()
        ->and($case->detected_by)->toBe($physician->id)
        ->and($case->classified_by)->toBe($physician->id);
});

test('a subcategory code opens a case for its parent disease', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);

    codeDiagnosis($physician, $encounter, 'Cholera due to Vibrio cholerae 01', 'A00.0');

    expect(SurveillanceCase::query()->count())->toBe(1)
        ->and(SurveillanceCase::query()->sole()->disease->name)->toBe('Cholera');
});

test('a weekly reportable disease opens a case for the IDSR 002 return without a deadline', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);

    codeDiagnosis($physician, $encounter, 'Unspecified malaria', 'B54');

    $case = SurveillanceCase::query()->sole();

    expect($case->disease->name)->toBe('Malaria')
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Weekly)
        ->and($case->notification_due_at)->toBeNull()
        ->and($case->notificationPhase())->toBe(SurveillanceCase::PHASE_NOT_REQUIRED);
});

test('a diagnosis that is not notifiable opens nothing', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);

    codeDiagnosis($physician, $encounter, 'Essential hypertension', 'I10');

    expect(SurveillanceCase::query()->count())->toBe(0);
});

test('the case snapshots the patient\'s residence for aggregation by LGA and state', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $patient = $encounter->patient;

    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();

    $lgaAtDetection = $patient->lga;
    $stateAtDetection = $patient->state;

    expect($case->residence_lga)->toBe($lgaAtDetection)
        ->and($case->residence_state)->toBe($stateAtDetection);

    $patient->update(['state' => 'Kano', 'lga' => 'Nassarawa']);
    $case->refresh();

    expect($case->residence_lga)->toBe($lgaAtDetection)
        ->and($case->residence_state)->toBe($stateAtDetection);
});

// ------------------------------------------------------------ Re-coding

test('re-coding a diagnosis moves the case to the new disease, or discards it when none applies', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $problem = codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    actingAs($physician)
        ->patch(route('encounters.problems.update', [$encounter, $problem]), [
            'name' => 'Gastroenteritis',
            'code' => 'A09',
            'status' => 'active',
            'role' => 'primary',
        ])
        ->assertRedirect();

    $case = SurveillanceCase::query()->whereMorphedTo('source', $problem)->sole();

    // A09 is itself weekly-reportable (acute diarrhoea), so the case moves and its rule snapshot follows.
    expect($case->disease->name)->toBe('Acute diarrhoea')
        ->and($case->category)->toBe(NotifiableDiseaseCategory::Weekly)
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Weekly)
        ->and($case->notification_due_at)->toBeNull();

    actingAs($physician)
        ->patch(route('encounters.problems.update', [$encounter, $problem]), [
            'name' => 'Essential hypertension',
            'code' => 'I10',
            'status' => 'active',
            'role' => 'primary',
        ])
        ->assertRedirect();

    expect($case->fresh()->classification)->toBe(CaseClassification::Discarded);
});

test('removing the diagnosis unlinks it and discards a case that was never notified', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $problem = codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    actingAs($physician)
        ->delete(route('encounters.problems.destroy', [$encounter, $problem]))
        ->assertRedirect();

    $case = SurveillanceCase::query()->sole();

    expect($case->classification)->toBe(CaseClassification::Discarded)
        ->and($case->source_type)->toBeNull()
        ->and($case->source_id)->toBeNull()
        ->and($case->notes)->toContain('Source record removed');
});

test('a notified case survives the diagnosis being removed', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $problem = codeDiagnosis($physician, $encounter, 'Lassa fever', 'A96.2');
    $case = SurveillanceCase::query()->sole();

    actingAs($physician)->post(route('surveillance.notify', $case), ['notified_to' => 'DSNO']);
    actingAs($physician)->delete(route('encounters.problems.destroy', [$encounter, $problem]));

    $case->refresh();

    expect($case->classification)->toBe(CaseClassification::Suspected)
        ->and($case->source_id)->toBeNull()
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Notified);
});

// ------------------------------------------------------------ Lifecycle

test('classification follows the lifecycle and records who moved it', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Measles', 'B05');
    $case = SurveillanceCase::query()->sole();
    $investigator = idsrUser(['chief-medical-director']);

    actingAs($investigator)
        ->patch(route('surveillance.update', $case), ['classification' => 'probable', 'outcome' => 'alive'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    actingAs($investigator)
        ->patch(route('surveillance.update', $case), ['classification' => 'confirmed', 'outcome' => 'dead', 'onset_date' => now()->subDays(4)->toDateString(), 'notes' => 'IgM positive.'])
        ->assertSessionHasNoErrors();

    $case->refresh();

    expect($case->classification)->toBe(CaseClassification::Confirmed)
        ->and($case->classified_by)->toBe($investigator->id)
        ->and($case->classified_at)->not->toBeNull()
        ->and($case->outcome->value)->toBe('dead')
        ->and($case->onset_date?->toDateString())->toBe(now()->subDays(4)->toDateString());
});

test('a confirmed case cannot be reclassified and a suspected case cannot skip to nothing', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Measles', 'B05');
    $case = SurveillanceCase::query()->sole();

    app(CaseSurveillance::class)->classify($case, CaseClassification::Confirmed, $physician);

    actingAs($physician)
        ->from(route('surveillance.show', $case))
        ->patch(route('surveillance.update', $case), ['classification' => 'suspected', 'outcome' => 'alive'])
        ->assertRedirect(route('surveillance.show', $case))
        ->assertSessionHasErrors('classification');

    expect($case->fresh()->classification)->toBe(CaseClassification::Confirmed)
        ->and(fn () => app(CaseSurveillance::class)->classify($case->fresh(), CaseClassification::Discarded))
        ->toThrow(ValidationException::class);
});

test('the case screen only offers the transitions the lifecycle allows', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Measles', 'B05');
    $case = SurveillanceCase::query()->sole();

    actingAs($physician)
        ->get(route('surveillance.show', $case))
        ->assertInertia(fn ($page) => $page
            ->where('classifications', fn ($options) => collect($options)->pluck('value')->all() === ['suspected', 'probable', 'confirmed', 'discarded']));

    app(CaseSurveillance::class)->classify($case, CaseClassification::Confirmed, $physician);

    actingAs($physician)
        ->get(route('surveillance.show', $case))
        ->assertInertia(fn ($page) => $page
            ->where('classifications', fn ($options) => collect($options)->pluck('value')->all() === ['confirmed']));
});

// ---------------------------------------------------------- Notification

test('recording the DSNO notification closes the loop and is on time within the deadline', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Lassa fever', 'A96.2');
    $case = SurveillanceCase::query()->sole();

    actingAs($physician)
        ->post(route('surveillance.notify', $case), [
            'notified_to' => 'DSNO Ikeja LGA, Mr Adewale',
            'notification_reference' => 'IDSR001/2026/014',
            'notes' => 'Reached by phone.',
        ])
        ->assertRedirect();

    $case->refresh();

    expect($case->notification_status)->toBe(CaseNotificationStatus::Notified)
        ->and($case->notified_by)->toBe($physician->id)
        ->and($case->notified_to)->toBe('DSNO Ikeja LGA, Mr Adewale')
        ->and($case->notification_reference)->toBe('IDSR001/2026/014')
        ->and($case->notes)->toContain('Reached by phone.')
        ->and($case->notificationPhase())->toBe(SurveillanceCase::PHASE_NOTIFIED);
});

test('a case past its deadline is overdue, and a late notification is recorded as late', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();

    $case->update(['detected_at' => now()->subHours(30), 'notification_due_at' => now()->subHours(6)]);

    expect($case->fresh()->notificationPhase())->toBe(SurveillanceCase::PHASE_OVERDUE)
        ->and(SurveillanceCase::query()->overdue()->count())->toBe(1);

    actingAs($physician)
        ->get(route('surveillance.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.overdue', 1)
            ->where('cases.data.0.overdue', true));

    actingAs($physician)->post(route('surveillance.notify', $case), ['notified_to' => 'DSNO']);

    expect($case->fresh()->notificationPhase())->toBe(SurveillanceCase::PHASE_NOTIFIED_LATE);
});

// ---------------------------------------------------------- Audit trail

test('every step of a case is in the audit trail with its before and after values', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $problem = codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();

    $created = caseAudit($case, 'created')->sole();
    expect($created->user_id)->toBe($physician->id)
        ->and($created->patient_id)->toBe($encounter->patient_id)
        ->and($created->new_values['classification'])->toBe('suspected');

    app(CaseSurveillance::class)->classify($case, CaseClassification::Probable, $physician);
    $classified = caseAudit($case, 'updated')->last();
    expect($classified->old_values['classification'])->toBe('suspected')
        ->and($classified->new_values['classification'])->toBe('probable');

    actingAs($physician)->post(route('surveillance.notify', $case), ['notified_to' => 'DSNO Ikeja']);
    $notified = caseAudit($case, 'updated')->last();
    expect($notified->old_values['notification_status'])->toBe('pending')
        ->and($notified->new_values['notification_status'])->toBe('notified')
        ->and($notified->new_values['notified_to'])->toBe('DSNO Ikeja');

    actingAs($physician)->patch(route('encounters.problems.update', [$encounter, $problem]), [
        'name' => 'Gastroenteritis', 'code' => 'A09', 'status' => 'active', 'role' => 'primary',
    ]);
    $recoded = caseAudit($case, 'updated')->last();
    expect($recoded->new_values['icd_code'])->toBe('A09')
        ->and($recoded->old_values['icd_code'])->toBe('A00');

    actingAs($physician)->delete(route('encounters.problems.destroy', [$encounter, $problem]));
    $unlinked = caseAudit($case, 'updated')->last();
    expect($unlinked->old_values['source_id'])->toBe($problem->id)
        ->and($unlinked->new_values['source_id'])->toBeNull();

    // The case itself was never deleted: the surveillance history is intact.
    expect(caseAudit($case, 'deleted'))->toHaveCount(0)
        ->and($case->fresh())->not->toBeNull();
});

// --------------------------------------------------------- Catalogue rules

test('deactivating or editing a catalogue entry does not rewrite historical cases', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();
    $originalDefinition = $case->case_definition;

    $cholera = NotifiableDisease::query()->where('slug', 'cholera')->sole();
    $cholera->update([
        'category' => NotifiableDiseaseCategory::Weekly,
        'notification_hours' => null,
        'case_definition' => 'Revised definition.',
        'is_active' => false,
    ]);

    $case->refresh();

    expect($case->category)->toBe(NotifiableDiseaseCategory::Immediate)
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Pending)
        ->and($case->notification_due_at)->not->toBeNull()
        ->and($case->case_definition)->toBe($originalDefinition);

    // New cholera diagnoses are no longer detected, even by a service instance
    // that was alive before the catalogue changed.
    codeDiagnosis($physician, idsrEncounter($physician), 'Cholera', 'A00');
    expect(SurveillanceCase::query()->count())->toBe(1);

    // ...but the retired disease still appears on the weekly summary for the period it has cases in.
    actingAs(idsrUser(['chief-medical-director']))
        ->get(route('reports.run', 'idsr-weekly-summary'))
        ->assertInertia(fn ($page) => $page
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('disease', 'Cholera (retired)')['cases'] === '1'));
});

test('other modules can open a case through the common interface without a diagnosis', function () {
    $nurse = idsrUser(['nurse']);
    $patient = Patient::factory()->create();

    $event = NotifiableDisease::query()->create([
        'name' => 'Maternal death',
        'slug' => 'maternal-death',
        'category' => NotifiableDiseaseCategory::Immediate,
        'detection' => NotifiableDisease::DETECTION_EVENT,
        'icd_prefixes' => [],
        'notification_hours' => 24,
        'is_active' => true,
    ]);

    $case = app(CaseSurveillance::class)->open($event, $patient, null, null, $nurse, ['notes' => 'Reported from the labour ward.']);

    expect($case->source_type)->toBeNull()
        ->and($case->notification_status)->toBe(CaseNotificationStatus::Pending)
        ->and($case->notification_due_at)->not->toBeNull()
        ->and($case->detected_by)->toBe($nurse->id)
        ->and($case->notes)->toBe('Reported from the labour ward.');

    // Event-type entries never match diagnosis codes.
    expect(app(CaseSurveillance::class)->match('O95'))->toBeNull();
});

test('opening a case twice for the same source returns the existing case', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    $problem = codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $cholera = NotifiableDisease::query()->where('slug', 'cholera')->sole();

    $again = app(CaseSurveillance::class)->open($cholera, $encounter->patient, $problem, $encounter, $physician);

    expect(SurveillanceCase::query()->count())->toBe(1)
        ->and($again->wasRecentlyCreated)->toBeFalse();
});

test('laboratory requisitions from the opening encounter are reachable from the case', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();

    LabOrder::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id]);
    LabOrder::factory()->create();

    expect($case->labOrders()->count())->toBe(1);
});

// -------------------------------------------------------- Screens & reports

test('the encounter screen carries the surveillance flag', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('surveillanceCases', 1)
            ->where('surveillanceCases.0.disease', 'Cholera')
            ->where('surveillanceCases.0.notification_status', 'pending')
        );
});

test('the register lists pending notifications first and can be filtered', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Unspecified malaria', 'B54');
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    actingAs($physician)
        ->get(route('surveillance.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('surveillance/Index')
            ->has('cases.data', 2)
            ->where('cases.data.0.disease', 'Cholera')
            ->where('summary.pending', 1)
            ->where('summary.this_week', 2)
        );

    actingAs($physician)
        ->get(route('surveillance.index', ['status' => 'weekly']))
        ->assertInertia(fn ($page) => $page
            ->has('cases.data', 1)
            ->where('cases.data.0.disease', 'Malaria')
        );
});

test('the case screen shows the patient contact details the DSNO will need', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    $case = SurveillanceCase::query()->sole();

    actingAs($physician)
        ->get(route('surveillance.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('surveillance/Show')
            ->where('case.disease', 'Cholera')
            ->where('case.requires_contact_tracing', true)
            ->where('case.patient.file_number', $encounter->patient->file_number)
            ->where('case.patient_details.phone', $encounter->patient->phone)
            ->where('case.problem.code', 'A00')
            ->has('case.case_definition')
            ->has('case.notification_due_at')
        );
});

test('the notifiable disease catalogue can be browsed and a disease switched off', function () {
    $physician = idsrUser();

    actingAs($physician)
        ->get(route('surveillance.diseases.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('surveillance/Diseases')
            ->where('diseases', fn ($rows) => collect($rows)->firstWhere('name', 'Cholera')['category'] === 'immediate'
                && collect($rows)->firstWhere('name', 'Cholera')['requires_contact_tracing'] === true
                && collect($rows)->firstWhere('name', 'Malaria')['notification_hours'] === null)
        );

    $cholera = NotifiableDisease::query()->where('slug', 'cholera')->sole();

    actingAs($physician)
        ->patch(route('surveillance.diseases.update', $cholera), ['is_active' => false])
        ->assertRedirect();

    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    expect(SurveillanceCase::query()->count())->toBe(0);
});

test('staff without the surveillance module are kept out', function () {
    actingAs(idsrUser(['cashier']))
        ->get(route('surveillance.index'))
        ->assertForbidden();
});

test('the IDSR line list and weekly summary report the open cases with residence and timeliness', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');
    codeDiagnosis($physician, $encounter, 'Unspecified malaria', 'B54');

    $cmd = idsrUser(['chief-medical-director']);

    actingAs($cmd)
        ->get(route('reports.run', 'idsr-line-list'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Report')
            ->has('rows', 2)
            ->where('rows.0.disease', 'Cholera')
            ->where('rows.0.file_number', $encounter->patient->file_number)
            ->where('rows.0.lga', $encounter->patient->lga)
            ->where('rows.0.state', $encounter->patient->state)
            ->where('rows.0.timeliness', 'Due')
            ->where('rows.1.timeliness', '—')
            ->where('summary', fn ($summary) => collect($summary)->firstWhere('label', 'Awaiting notification')['value'] === '1'
                && collect($summary)->firstWhere('label', 'Notified on time')['value'] === '0 of 1')
        );

    actingAs($cmd)
        ->get(route('reports.run', 'idsr-weekly-summary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('disease', 'Cholera')['cases'] === '1'
                && collect($rows)->firstWhere('disease', 'Malaria')['cases'] === '1'
                && collect($rows)->firstWhere('disease', 'Measles')['cases'] === '0')
        );
});

test('the home screen warns about cases awaiting notification', function () {
    $physician = idsrUser();
    $encounter = idsrEncounter($physician);
    codeDiagnosis($physician, $encounter, 'Cholera', 'A00');

    actingAs($physician)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('home.alerts', fn ($alerts) => collect($alerts)->firstWhere('key', 'notifiable_cases')['count'] === 1)
        );
});

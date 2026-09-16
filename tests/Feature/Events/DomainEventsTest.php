<?php

use App\Events\DiagnosisRecorded;
use App\Events\PatientRegistered;
use App\Models\DomainEventRecord;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\QueueEntry;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Services\DomainEvents;
use Database\Seeders\IcdCodesSeeder;
use Database\Seeders\NotifiableDiseasesSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function eventUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to consultation and open the encounter as the physician.
 */
function eventEncounter(User $physician): Encounter
{
    $patient = Patient::factory()->create();

    actingAs(eventUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);
}

// ------------------------------------------------------------ The publisher

test('publishing journals the event and hands it to its listeners in order', function () {
    $actor = eventUser(['records-officer']);
    $patient = Patient::factory()->create();
    $seen = [];

    Event::listen(PatientRegistered::class, function (PatientRegistered $event) use (&$seen) {
        // The journal entry is already there when a listener runs.
        $seen[] = DomainEventRecord::where('name', 'patient_registered')->count();
    });

    $record = app(DomainEvents::class)->publish(new PatientRegistered($patient, $actor));

    expect($seen)->toBe([1])
        ->and($record->name)->toBe('patient_registered')
        ->and($record->subject_type)->toBe(Patient::class)
        ->and($record->subject_id)->toBe($patient->id)
        ->and($record->patient_id)->toBe($patient->id)
        ->and($record->actor_id)->toBe($actor->id)
        ->and($record->payload['file_number'])->toBe($patient->file_number)
        ->and($record->ulid)->toHaveLength(26)
        ->and($record->occurred_at)->not->toBeNull();
});

test('an event published inside a write that rolls back leaves no trace', function () {
    $patient = Patient::factory()->create();

    try {
        DB::transaction(function () use ($patient) {
            app(DomainEvents::class)->publish(new PatientRegistered($patient));
            throw new RuntimeException('abandon');
        });
    } catch (RuntimeException) {
    }

    expect(DomainEventRecord::count())->toBe(0);
});

test('the journal is append-only', function () {
    $record = app(DomainEvents::class)->publish(new PatientRegistered(Patient::factory()->create()));

    expect(fn () => $record->forceFill(['name' => 'tampered'])->save())->toThrow(LogicException::class)
        ->and(fn () => $record->delete())->toThrow(LogicException::class);
});

// ------------------------------------------------------------- The producers

test('registering a patient publishes patient_registered', function () {
    actingAs(eventUser(['records-officer']))
        ->post(route('patients.store'), [
            'surname' => 'Okafor', 'first_name' => 'Ada', 'sex' => 'F', 'nationality' => 'Nigerian',
            'state' => 'Lagos', 'lga' => 'Ikeja', 'coverage' => 'private', 'is_transfer' => false,
            'visit_category' => 'Outpatient', 'outpatient_service' => 'Clinical Consultation & Diagnosis',
        ])
        ->assertSessionHasNoErrors();

    $patient = Patient::latest('id')->firstOrFail();
    $record = DomainEventRecord::where('name', 'patient_registered')->firstOrFail();

    expect($record->subject_id)->toBe($patient->id)
        ->and($record->payload['file_number'])->toBe($patient->file_number);
});

test('editing a patient publishes patient_updated with the attributes that changed', function () {
    $patient = Patient::factory()->create(['phone' => '08010000000', 'coverage' => 'private', 'hmo_name' => null, 'hmo_number' => null]);
    $officer = eventUser(['records-officer']);

    actingAs($officer)
        ->patch(route('patients.update', $patient), [
            ...$patient->only(['title', 'surname', 'first_name', 'other_names', 'sex', 'marital_status', 'email', 'address', 'nationality', 'state', 'lga', 'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'coverage', 'visit_category', 'outpatient_service']),
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'is_transfer' => false,
            'phone' => '08099999999',
        ])
        ->assertSessionHasNoErrors();

    $record = DomainEventRecord::where('name', 'patient_updated')->firstOrFail();

    expect($record->patient_id)->toBe($patient->id)
        ->and($record->actor_id)->toBe($officer->id)
        ->and($record->payload['changed'])->toContain('phone');
});

test('coding, re-coding and removing a diagnosis publish diagnosis events', function () {
    $physician = eventUser(['physician']);
    $encounter = eventEncounter($physician);

    actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), ['name' => 'Malaria', 'code' => 'B54', 'status' => 'active', 'role' => 'primary'])
        ->assertRedirect();
    $problem = Problem::latest('id')->firstOrFail();

    actingAs($physician)
        ->patch(route('encounters.problems.update', [$encounter, $problem]), ['name' => 'Typhoid', 'code' => 'A01', 'status' => 'active', 'role' => 'primary'])
        ->assertRedirect();
    actingAs($physician)
        ->delete(route('encounters.problems.destroy', [$encounter, $problem]))
        ->assertRedirect();

    $names = DomainEventRecord::query()
        ->where('subject_type', Problem::class)
        ->where('subject_id', $problem->id)
        ->orderBy('id')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['diagnosis_recorded', 'diagnosis_recorded', 'diagnosis_removed'])
        ->and(DomainEventRecord::where('name', 'diagnosis_recorded')->latest('id')->first()->payload['code'])->toBe('A01');
});

// ------------------------------------------------------------- The listeners

test('surveillance screening runs as a listener on diagnosis events', function () {
    $this->seed(IcdCodesSeeder::class);
    $this->seed(NotifiableDiseasesSeeder::class);

    $physician = eventUser(['physician']);
    $encounter = eventEncounter($physician);

    $response = actingAs($physician)
        ->post(route('encounters.problems.store', $encounter), ['name' => 'Cholera', 'code' => 'A00', 'status' => 'active', 'role' => 'primary']);

    $problem = Problem::latest('id')->firstOrFail();
    $case = SurveillanceCase::query()->whereMorphedTo('source', $problem)->first();

    expect($case)->not->toBeNull()
        ->and($case->patient_id)->toBe($encounter->patient_id)
        ->and($case->detected_by)->toBe($physician->id);

    // The clinician is warned in the same request.
    $response->assertRedirect()->assertSessionHas('inertia.flash_data', fn (array $flash) => ($flash['toast']['type'] ?? null) === 'warning');

    actingAs($physician)->delete(route('encounters.problems.destroy', [$encounter, $problem]));

    expect($case->fresh()->classification->value)->toBe('discarded')
        ->and($case->fresh()->source_id)->toBeNull();
});

test('a listener that fails aborts the write that caused the event', function () {
    Event::listen(DiagnosisRecorded::class, function (): void {
        throw new RuntimeException('surveillance is down');
    });

    $physician = eventUser(['physician']);
    $encounter = eventEncounter($physician);

    expect(fn () => DB::transaction(function () use ($encounter, $physician) {
        $problem = $encounter->patient->problems()->create(['name' => 'Malaria', 'code' => 'B54', 'status' => 'active', 'encounter_id' => $encounter->id]);
        app(DomainEvents::class)->publish(new DiagnosisRecorded($problem, $physician));
    }))->toThrow(RuntimeException::class);

    expect(Problem::count())->toBe(0)
        ->and(DomainEventRecord::where('name', 'diagnosis_recorded')->count())->toBe(0);
});

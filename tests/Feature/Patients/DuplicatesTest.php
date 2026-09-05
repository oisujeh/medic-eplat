<?php

use App\Enums\IdentifierType;
use App\Enums\MatchCandidateStatus;
use App\Enums\MatchLevel;
use App\Enums\MatchSignal;
use App\Models\AuditLog;
use App\Models\DomainEventRecord;
use App\Models\Encounter;
use App\Models\MatchingRule;
use App\Models\Patient;
use App\Models\PatientMatchCandidate;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\DuplicateReview;
use App\Services\MatchingRules;
use App\Services\PatientMatcher;
use App\Services\PatientMerger;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function dedupUser(array $roleSlugs): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Two records that read as one person: same name, date of birth and sex.
 *
 * @param  array<string, mixed>  $second
 * @return array{0: Patient, 1: Patient}
 */
function probablePair(array $second = []): array
{
    $base = ['surname' => 'Okoro', 'first_name' => 'Chidi', 'date_of_birth' => '1985-03-09', 'sex' => 'M', 'lga' => 'Ikeja', 'nin' => null, 'phone' => null];

    $a = Patient::factory()->create($base);
    $b = Patient::factory()->create([...$base, 'phone' => null, ...$second]);

    return [$a, $b];
}

// ------------------------------------------------------------------- Scoring

test('a shared NIN settles it on its own', function () {
    $a = Patient::factory()->create(['nin' => '12345678901', 'surname' => 'Bello', 'first_name' => 'Musa', 'sex' => 'M']);
    $b = Patient::factory()->create(['nin' => '12345678901', 'surname' => 'Belo', 'first_name' => 'Moussa', 'sex' => 'M']);

    $result = app(PatientMatcher::class)->score($a, $b);

    expect($result->level)->toBe(MatchLevel::Auto)
        ->and($result->signals)->toContain(MatchSignal::NinExact, MatchSignal::SurnameSounds, MatchSignal::FirstNameSounds);
});

test('the same name, date of birth and sex is a probable match; a different sex is not', function () {
    [$a, $b] = probablePair();
    $c = Patient::factory()->create(['surname' => 'Okoro', 'first_name' => 'Chidi', 'date_of_birth' => '1985-03-09', 'sex' => 'F', 'lga' => 'Ikeja']);

    $matcher = app(PatientMatcher::class);

    expect($matcher->score($a, $b)->level)->toBe(MatchLevel::Review)
        ->and($matcher->score($a, $c)->level)->toBe(MatchLevel::None)
        ->and($matcher->score($a, $c)->signals)->toContain(MatchSignal::SexDiffers);
});

test('the same name alone is only a hint', function () {
    $a = Patient::factory()->create(['surname' => 'Okoro', 'first_name' => 'Chidi', 'date_of_birth' => '1985-03-09', 'sex' => 'M', 'lga' => 'Ikeja']);
    $b = Patient::factory()->create(['surname' => 'Okoro', 'first_name' => 'Chidi', 'date_of_birth' => '1962-11-20', 'sex' => 'M', 'lga' => 'Surulere']);

    expect(app(PatientMatcher::class)->score($a, $b)->level)->toBe(MatchLevel::Hint);
});

test('swapped names and a shared parent still count', function () {
    $a = Patient::factory()->create(['surname' => 'Amaka', 'first_name' => 'Nwosu', 'date_of_birth' => '2019-05-05', 'sex' => 'F', 'next_of_kin_name' => 'Ngozi Nwosu', 'next_of_kin_relationship' => 'Parent']);
    $b = Patient::factory()->create(['surname' => 'Nwosu', 'first_name' => 'Amaka', 'date_of_birth' => '2019-05-05', 'sex' => 'F', 'next_of_kin_name' => 'Ngozi Nwosu', 'next_of_kin_relationship' => 'Parent']);

    $result = app(PatientMatcher::class)->score($a, $b);

    expect($result->signals)->toContain(MatchSignal::NamesSwapped, MatchSignal::ParentSame, MatchSignal::DobExact)
        ->and($result->level)->toBe(MatchLevel::Review);
});

test('weights and thresholds are configuration, not code', function () {
    [$a, $b] = probablePair();
    $matcher = app(PatientMatcher::class);

    expect($matcher->score($a, $b)->level)->toBe(MatchLevel::Review);

    MatchingRule::create(['signal' => MatchSignal::DobExact->value, 'weight' => 0, 'is_active' => true]);
    app(MatchingRules::class)->forget();

    expect(app(PatientMatcher::class)->score($a, $b)->level)->toBe(MatchLevel::Hint);

    app(MatchingRules::class)->saveThresholds(100, 30, 10);

    expect(app(PatientMatcher::class)->score($a, $b)->level)->toBe(MatchLevel::Review);
});

// ----------------------------------------------------------------- The queue

test('registering a probable duplicate queues the pair for review', function () {
    $existing = Patient::factory()->create(['surname' => 'Okoro', 'first_name' => 'Chidi', 'date_of_birth' => '1985-03-09', 'sex' => 'M', 'lga' => 'Ikeja']);

    actingAs(dedupUser(['records-officer']))
        ->post(route('patients.store'), [
            'surname' => 'Okoro', 'first_name' => 'Chidi', 'sex' => 'M', 'date_of_birth' => '1985-03-09',
            'nationality' => 'Nigerian', 'state' => 'Lagos', 'lga' => 'Ikeja', 'coverage' => 'private', 'is_transfer' => false,
            'visit_category' => 'Outpatient', 'outpatient_service' => 'Clinical Consultation & Diagnosis',
        ])
        ->assertSessionHasNoErrors();

    $new = Patient::latest('id')->firstOrFail();
    $candidate = PatientMatchCandidate::query()->firstOrFail();

    expect($candidate->patient_id)->toBe(min($existing->id, $new->id))
        ->and($candidate->candidate_id)->toBe(max($existing->id, $new->id))
        ->and($candidate->status)->toBe(MatchCandidateStatus::Pending)
        ->and($candidate->level)->toBe(MatchLevel::Review)
        ->and($candidate->signalLabels())->toContain('Same date of birth');

    // The queue is reachable and counted where records staff work.
    actingAs(dedupUser(['records-officer']))
        ->get(route('patients.index'))
        ->assertInertia(fn ($page) => $page->where('duplicatesPending', 1));

    actingAs(dedupUser(['records-officer']))
        ->get(route('patients.duplicates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('patients/Duplicates')->has('candidates.data', 1));
});

test('a dismissed pair is not queued again when the record is edited', function () {
    [$a, $b] = probablePair();
    $officer = dedupUser(['records-officer']);
    app(DuplicateReview::class)->refresh($a);
    $candidate = PatientMatchCandidate::query()->firstOrFail();

    actingAs($officer)
        ->post(route('patients.duplicates.dismiss', $candidate), ['notes' => 'Father and son'])
        ->assertRedirect(route('patients.duplicates.index', absolute: false));

    expect($candidate->fresh()->status)->toBe(MatchCandidateStatus::Dismissed)
        ->and($candidate->fresh()->reviewed_by)->toBe($officer->id);

    app(DuplicateReview::class)->refresh($a);

    expect(PatientMatchCandidate::count())->toBe(1)
        ->and($candidate->fresh()->status)->toBe(MatchCandidateStatus::Dismissed);
});

test('the review screen compares the two records', function () {
    [$a, $b] = probablePair();
    app(DuplicateReview::class)->refresh($a);
    $candidate = PatientMatchCandidate::query()->firstOrFail();

    actingAs(dedupUser(['records-officer']))
        ->get(route('patients.duplicates.show', $candidate))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('patients/DuplicateReview')
            ->where('candidate.level', 'review')
            ->has('records', 2)
            ->where('canMerge', true));
});

// ----------------------------------------------------------------- Merging

test('merging moves every record to the survivor, keeps the old file number and leaves a tombstone', function () {
    [$survivor, $duplicate] = probablePair(['phone' => '08031234567', 'email' => 'chidi@example.com']);
    $survivor->update(['email' => null, 'phone' => null]);

    $visit = Visit::factory()->for($duplicate)->create();
    Encounter::factory()->create(['patient_id' => $duplicate->id, 'visit_id' => $visit->id]);
    Visit::factory()->for($survivor)->create();
    $child = Patient::factory()->create(['mother_patient_id' => $duplicate->id]);

    app(DuplicateReview::class)->refresh($survivor);
    $candidate = PatientMatchCandidate::query()->firstOrFail();
    $officer = dedupUser(['records-officer']);

    actingAs($officer)
        ->post(route('patients.duplicates.merge', $candidate), ['survivor_id' => $survivor->id, 'reason' => 'Same man, registered twice at the desk'])
        ->assertRedirect(route('patients.show', $survivor, absolute: false));

    $survivor->refresh();
    $duplicate->refresh();

    expect($survivor->visits()->count())->toBe(2)
        ->and($survivor->encounters()->count())->toBe(1)
        ->and($duplicate->visits()->count())->toBe(0)
        ->and($child->fresh()->mother_patient_id)->toBe($survivor->id)
        // Blanks on the survivor are filled from the duplicate.
        ->and($survivor->phone)->toBe('08031234567')
        ->and($survivor->email)->toBe('chidi@example.com')
        // The duplicate's card still finds the chart.
        ->and($survivor->identifiers()->where('type', IdentifierType::PreviousFileNumber->value)->value('value'))->toBe($duplicate->file_number)
        ->and($duplicate->identifiers()->count())->toBe(0)
        // The duplicate is a tombstone pointing at the survivor.
        ->and($duplicate->merged_into_id)->toBe($survivor->id)
        ->and($duplicate->merged_at)->not->toBeNull()
        ->and($candidate->fresh()->status)->toBe(MatchCandidateStatus::Merged);

    // The merge is one audited action with a reason and what moved.
    $log = AuditLog::query()->where('action', AuditLog::ACTION_MERGED)->firstOrFail();

    expect($log->patient_id)->toBe($survivor->id)
        ->and($log->reason)->toBe('Same man, registered twice at the desk')
        ->and($log->old_values['merged_from'])->toBe($duplicate->file_number)
        ->and($log->new_values['moved']['visits'])->toBe(1)
        ->and(DomainEventRecord::where('name', 'patients_merged')->exists())->toBeTrue();

    // The tombstone is hidden from the register and redirects to the survivor.
    actingAs($officer)
        ->get(route('patients.index', ['search' => $duplicate->file_number]))
        ->assertInertia(fn ($page) => $page->where('patients.data', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $survivor->id));

    actingAs($officer)
        ->get(route('patients.show', $duplicate))
        ->assertRedirect(route('patients.show', $survivor, absolute: false));
});

test('merging needs the permission and a record can only be merged once', function () {
    [$a, $b] = probablePair();
    app(DuplicateReview::class)->refresh($a);
    $candidate = PatientMatchCandidate::query()->firstOrFail();

    actingAs(dedupUser(['nurse']))
        ->post(route('patients.duplicates.merge', $candidate), ['survivor_id' => $a->id])
        ->assertForbidden();

    $officer = dedupUser(['records-officer']);
    app(PatientMerger::class)->merge($a, $b, $officer);

    expect(fn () => app(PatientMerger::class)->merge($a, $b->fresh(), $officer))
        ->toThrow(ValidationException::class);
});

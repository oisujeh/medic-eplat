<?php

use App\Enums\ReferralStatus;
use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\Referral;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\ReferralService;
use Database\Seeders\IcdCodesSeeder;
use Database\Seeders\RolesAndModulesSeeder;
use Database\Seeders\ServicePointsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    $this->seed(ServicePointsSeeder::class);
    $this->seed(IcdCodesSeeder::class);
});

/**
 * @param  array<int, string>  $roleSlugs
 */
function refUser(array $roleSlugs = ['physician']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

/**
 * Route a patient to consultation and open the encounter as the physician.
 */
function refEncounter(User $physician): Encounter
{
    $patient = Patient::factory()->create();
    actingAs(refUser(['records-officer']))->post(route('patients.route', $patient), [
        'service_point_id' => ServicePoint::where('slug', 'consultation')->firstOrFail()->id,
        'priority' => 'normal',
    ]);

    return openEncounter(QueueEntry::latest('id')->firstOrFail(), $physician);
}

/**
 * A valid referral submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function referralPayload(array $overrides = []): array
{
    return [
        'destination_facility' => 'Lagos University Teaching Hospital',
        'destination_department' => 'Cardiology',
        'destination_contact' => 'Dr Bello, 0803 000 0000',
        'urgency' => 'urgent',
        'reason' => 'Echocardiography and specialist review for suspected heart failure.',
        'diagnosis' => 'Congestive heart failure',
        'clinical_summary' => 'Two weeks of breathlessness and leg swelling.',
        'treatment_given' => 'Furosemide 40 mg IV stat',
        ...$overrides,
    ];
}

test('a physician issues a referral from the encounter and it is numbered by year', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);

    actingAs($physician)
        ->post(route('encounters.referrals.store', $encounter), referralPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $referral = Referral::query()->sole();

    expect($referral->referral_number)->toBe(sprintf('REF/%d/000001', now()->year))
        ->and($referral->patient_id)->toBe($encounter->patient_id)
        ->and($referral->encounter_id)->toBe($encounter->id)
        ->and($referral->referred_by)->toBe($physician->id)
        ->and($referral->status)->toBe(ReferralStatus::Issued)
        ->and($referral->urgency->value)->toBe('urgent')
        ->and($referral->destination_facility)->toBe('Lagos University Teaching Hospital');

    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());

    expect(Referral::query()->latest('id')->first()->referral_number)->toBe(sprintf('REF/%d/000002', now()->year));
});

test('a referral needs a destination and a reason', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);

    actingAs($physician)
        ->from(route('encounters.show', $encounter))
        ->post(route('encounters.referrals.store', $encounter), referralPayload(['destination_facility' => '', 'reason' => '']))
        ->assertSessionHasErrors(['destination_facility', 'reason']);

    expect(Referral::query()->count())->toBe(0);
});

test('only someone who may document the encounter can refer from it', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);

    actingAs(refUser(['cashier']))
        ->post(route('encounters.referrals.store', $encounter), referralPayload())
        ->assertForbidden();
});

test('the encounter pre-fills the referral from the diagnosis, assessment and treatment', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);

    actingAs($physician)->post(route('encounters.problems.store', $encounter), [
        'name' => 'Unspecified malaria', 'code' => 'B54', 'status' => 'active', 'role' => 'primary',
    ]);
    actingAs($physician)->post(route('encounters.medications.store', $encounter), [
        'name' => 'Artemether-lumefantrine', 'dose' => '80/480 mg', 'frequency' => 'BD', 'route' => 'PO',
    ]);
    $encounter->update(['assessment' => 'Severe malaria with anaemia.', 'plan' => 'Refer for transfusion.']);

    actingAs($physician)
        ->get(route('encounters.show', $encounter))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('referralDraft.diagnosis', 'Unspecified malaria (B54)')
            ->where('referralDraft.clinical_summary', fn ($s) => str_contains($s, 'Severe malaria') && str_contains($s, 'Refer for transfusion'))
            ->where('referralDraft.treatment_given', fn ($t) => str_contains($t, 'Artemether-lumefantrine'))
            ->has('referrals', 0)
            ->has('encounter.urls.referrals')
        );
});

test('the referral letter downloads as a PDF and records that it was printed', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());
    $referral = Referral::query()->sole();

    $response = actingAs($physician)->get(route('referrals.letter', $referral));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and($referral->fresh()->printed_at)->not->toBeNull();
});

test('signing with the outcome Referred requires a referral record', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);

    $sign = fn () => actingAs($physician)
        ->from(route('encounters.show', $encounter))
        ->post(route('encounters.sign', $encounter), [
            'presenting_complaint' => 'Chest pain',
            'assessment' => 'Suspected acute coronary syndrome',
            'plan' => 'Refer',
            'outcome' => 'referred',
        ]);

    $sign()->assertSessionHasErrors('outcome');

    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());

    $sign()->assertSessionHasNoErrors();
    expect($encounter->fresh()->signed_at)->not->toBeNull();
});

test('the register lists open referrals first, filters by status and shows how long feedback is outstanding', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload(['destination_facility' => 'Ikeja General Hospital']));

    $stale = Referral::query()->orderBy('id')->first();
    $stale->update(['referred_at' => now()->subDays(20)]);
    $closed = Referral::query()->orderByDesc('id')->first();
    app(ReferralService::class)->setStatus($closed, ReferralStatus::Seen, 'Admitted for observation.', $physician);

    actingAs($physician)
        ->get(route('referrals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('referrals/Index')
            ->has('referrals.data', 2)
            ->where('referrals.data.0.id', $stale->id)
            ->where('referrals.data.0.days_open', 20)
            ->where('summary.open', 1)
            ->where('summary.awaiting_feedback', 1)
        );

    actingAs($physician)
        ->get(route('referrals.index', ['status' => 'seen']))
        ->assertInertia(fn ($page) => $page
            ->has('referrals.data', 1)
            ->where('referrals.data.0.id', $closed->id)
            ->where('referrals.data.0.feedback', 'Admitted for observation.')
        );
});

test('feedback from the receiving facility closes the referral and is audited', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());
    $referral = Referral::query()->sole();
    $records = refUser(['records-officer']);

    actingAs($records)
        ->post(route('referrals.status', $referral), ['status' => 'accepted'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    actingAs($records)
        ->post(route('referrals.status', $referral), ['status' => 'seen', 'feedback' => 'Seen in cardiology clinic, echo done, follow up in 4 weeks.'])
        ->assertSessionHasNoErrors();

    $referral->refresh();

    expect($referral->status)->toBe(ReferralStatus::Seen)
        ->and($referral->feedback)->toContain('echo done')
        ->and($referral->feedback_at)->not->toBeNull()
        ->and($referral->closed_by)->toBe($records->id);

    // A closed referral cannot be reopened.
    actingAs($records)
        ->from(route('referrals.show', $referral))
        ->post(route('referrals.status', $referral), ['status' => 'issued'])
        ->assertSessionHasErrors('status');

    $updates = AuditLog::query()
        ->where('auditable_type', Referral::class)
        ->where('auditable_id', $referral->id)
        ->where('action', 'updated')
        ->orderBy('id')
        ->get();

    expect($updates->first()->old_values['status'])->toBe('issued')
        ->and($updates->first()->new_values['status'])->toBe('accepted')
        ->and($updates->last()->new_values['status'])->toBe('seen');
});

test('the referral screen offers only the transitions its status allows', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());
    $referral = Referral::query()->sole();

    actingAs($physician)
        ->get(route('referrals.show', $referral))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('referrals/Show')
            ->where('referral.referral_number', $referral->referral_number)
            ->where('referral.patient.file_number', $encounter->patient->file_number)
            ->where('referral.transitions', fn ($t) => collect($t)->pluck('value')->all() === ['accepted', 'seen', 'declined', 'cancelled'])
        );
});

test('staff without the referrals module are kept out of the register', function () {
    actingAs(refUser(['cashier']))
        ->get(route('referrals.index'))
        ->assertForbidden();
});

test('the referral register report lists referrals in the period', function () {
    $physician = refUser();
    $encounter = refEncounter($physician);
    actingAs($physician)->post(route('encounters.referrals.store', $encounter), referralPayload());

    actingAs(refUser(['chief-medical-director']))
        ->get(route('reports.run', 'referral-register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.destination', 'Lagos University Teaching Hospital · Cardiology')
            ->where('rows.0.status', 'Issued')
        );
});

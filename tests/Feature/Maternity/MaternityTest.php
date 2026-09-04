<?php

use App\Enums\BirthOutcome;
use App\Enums\PregnancyStatus;
use App\Models\Admission;
use App\Models\Birth;
use App\Models\Delivery;
use App\Models\Encounter;
use App\Models\ObservationSet;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\Role;
use App\Models\User;
use App\Models\Ward;
use App\Services\NhmisReports;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    Carbon::setTestNow('2026-09-04 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<int, string>  $roleSlugs
 */
function midwife(array $roleSlugs = ['nurse']): User
{
    $user = User::factory()->create();
    $user->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));

    return $user->fresh();
}

function mother(): Patient
{
    return Patient::factory()->create(['sex' => 'F', 'date_of_birth' => '1998-03-10', 'surname' => 'Okafor', 'first_name' => 'Ngozi']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function deliveryPayload(array $overrides = []): array
{
    return array_merge([
        'delivered_at' => '2026-09-03 14:30',
        'mode' => 'svd',
        'labour_onset' => 'spontaneous',
        'place' => 'facility',
        'complications' => ['Perineal tear (3rd/4th degree)'],
        'blood_loss_ml' => 350,
        'maternal_outcome' => 'well',
        'births' => [
            ['outcome' => 'live', 'sex' => 'M', 'weight_grams' => 3100, 'apgar_1' => 8, 'apgar_5' => 9, 'breastfed_within_hour' => true, 'bcg_given' => true, 'condition' => 'well'],
            ['outcome' => 'stillbirth_fresh', 'sex' => 'F', 'weight_grams' => 2100],
        ],
    ], $overrides);
}

test('a nurse can open the antenatal register', function () {
    Pregnancy::factory()->count(2)->create();

    actingAs(midwife())
        ->get(route('maternity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('maternity/Index')
            ->has('pregnancies', 2)
            ->where('stats.active', 2)
        );
});

test('staff without the maternity module are forbidden', function () {
    actingAs(midwife(['cashier']))
        ->get(route('maternity.index'))
        ->assertForbidden();
});

test('booking derives the EDD from the LMP and numbers the pregnancy', function () {
    $patient = mother();

    actingAs(midwife())
        ->post(route('maternity.store'), [
            'patient_id' => $patient->id,
            'lmp' => '2026-05-01',
            'gravida' => 2,
            'para' => 1,
            'risk_factors' => ['Previous caesarean section'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $pregnancy = Pregnancy::firstOrFail();

    expect($pregnancy->pregnancy_number)->toStartWith('PRG/')
        ->and($pregnancy->status)->toBe(PregnancyStatus::Active)
        ->and($pregnancy->edd->toDateString())->toBe('2027-02-05')
        ->and($pregnancy->gestationalAgeWeeks())->toBe(18)
        ->and($pregnancy->risk_factors)->toBe(['Previous caesarean section'])
        ->and($pregnancy->booking_date->toDateString())->toBe('2026-09-04')
        ->and($patient->activePregnancy()?->id)->toBe($pregnancy->id);
});

test('booking needs a date and a female patient without an active pregnancy', function () {
    $man = Patient::factory()->create(['sex' => 'M']);
    $woman = mother();

    actingAs(midwife())
        ->from(route('maternity.index'))
        ->post(route('maternity.store'), ['patient_id' => $woman->id])
        ->assertSessionHasErrors('lmp');

    actingAs(midwife())
        ->post(route('maternity.store'), ['patient_id' => $man->id, 'lmp' => '2026-05-01'])
        ->assertSessionHasErrors('patient_id');

    actingAs(midwife())->post(route('maternity.store'), ['patient_id' => $woman->id, 'lmp' => '2026-05-01']);

    actingAs(midwife())
        ->post(route('maternity.store'), ['patient_id' => $woman->id, 'edd' => '2027-03-01'])
        ->assertSessionHasErrors('patient_id');

    expect(Pregnancy::count())->toBe(1);
});

test('the pregnancy record shows ANC visits since booking', function () {
    $patient = mother();
    Encounter::factory()->for($patient)->nursing('anc')->signed()->create(['signed_at' => '2026-06-01 10:00']);

    $pregnancy = Pregnancy::factory()->create(['patient_id' => $patient->id, 'lmp' => '2026-05-01', 'edd' => '2027-02-05']);
    $pregnancy->forceFill(['created_at' => '2026-07-01 09:00'])->save();

    $visit = Encounter::factory()->for($patient)->nursing('anc')->signed()->create(['signed_at' => '2026-08-10 10:00']);
    ObservationSet::factory()->for($patient)->for($visit)->withReadings([
        'gestational_age' => 14, 'fundal_height' => 15, 'fetal_heart_rate' => 148, 'presentation' => 'Cephalic',
    ])->create(['recorded_at' => '2026-08-10 10:05']);
    Encounter::factory()->for($patient)->nursing('triage')->signed()->create(['signed_at' => '2026-08-11 10:00']);

    actingAs(midwife())
        ->get(route('maternity.show', $pregnancy))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('maternity/Show')
            ->where('pregnancy.status', 'active')
            ->has('ancVisits', 1)
            ->where('ancVisits.0.fetal_heart_rate', 148)
            ->where('delivery', null)
        );
});

test('recording a delivery closes the pregnancy and registers each baby', function () {
    $patient = mother();
    $pregnancy = Pregnancy::factory()->create(['patient_id' => $patient->id, 'lmp' => '2025-12-10', 'edd' => '2026-09-16']);
    $ward = Ward::factory()->withBeds(1)->create();
    $admission = Admission::factory()->create(['patient_id' => $patient->id, 'ward_id' => $ward->id, 'status' => 'admitted', 'admitted_at' => '2026-09-03 08:00']);
    $attendant = midwife();

    actingAs($attendant)
        ->post(route('maternity.delivery.store', $pregnancy), deliveryPayload(['attendant_id' => $attendant->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $pregnancy->refresh();
    $delivery = Delivery::firstOrFail();

    expect($pregnancy->status)->toBe(PregnancyStatus::Delivered)
        ->and($pregnancy->closed_at)->not->toBeNull()
        ->and($delivery->admission_id)->toBe($admission->id)
        ->and($delivery->gestational_age_weeks)->toBe(38)
        ->and($delivery->complications)->toBe(['Perineal tear (3rd/4th degree)'])
        ->and($delivery->births()->count())->toBe(2)
        ->and($delivery->births()->where('birth_order', 1)->first()->outcome)->toBe(BirthOutcome::Live)
        ->and($delivery->births()->where('birth_order', 2)->first()->outcome)->toBe(BirthOutcome::StillbirthFresh)
        ->and($delivery->births()->where('birth_order', 2)->first()->isLowBirthWeight())->toBeTrue()
        ->and($patient->activePregnancy())->toBeNull();

    // A delivered pregnancy takes no second delivery.
    actingAs($attendant)
        ->post(route('maternity.delivery.store', $pregnancy), deliveryPayload())
        ->assertSessionHasErrors('status');
});

test('a delivery must have at least one baby with an outcome and sex', function () {
    $pregnancy = Pregnancy::factory()->create();

    actingAs(midwife())
        ->post(route('maternity.delivery.store', $pregnancy), deliveryPayload(['births' => []]))
        ->assertSessionHasErrors('births');

    actingAs(midwife())
        ->post(route('maternity.delivery.store', $pregnancy), deliveryPayload(['births' => [['outcome' => 'live']]]))
        ->assertSessionHasErrors('births.0.sex');

    expect(Delivery::count())->toBe(0)
        ->and($pregnancy->fresh()->status)->toBe(PregnancyStatus::Active);
});

test('a live-born baby can be registered as a patient linked to the mother', function () {
    $patient = mother();
    $pregnancy = Pregnancy::factory()->create(['patient_id' => $patient->id]);
    actingAs(midwife())->post(route('maternity.delivery.store', $pregnancy), deliveryPayload());

    $live = Birth::where('outcome', BirthOutcome::Live->value)->firstOrFail();
    $still = Birth::where('outcome', BirthOutcome::StillbirthFresh->value)->firstOrFail();

    actingAs(midwife(['records-officer']))
        ->post(route('maternity.births.register', $live))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $baby = Patient::where('first_name', 'Baby of Ngozi')->firstOrFail();

    expect($baby->surname)->toBe('Okafor')
        ->and($baby->sex)->toBe('M')
        ->and($baby->date_of_birth->toDateString())->toBe('2026-09-03')
        ->and($baby->next_of_kin_name)->toBe($patient->fullName())
        ->and($baby->file_number)->toStartWith('MEP/')
        ->and($live->fresh()->newborn_patient_id)->toBe($baby->id);

    actingAs(midwife())
        ->post(route('maternity.births.register', $live))
        ->assertSessionHasErrors('birth');

    actingAs(midwife())
        ->post(route('maternity.births.register', $still))
        ->assertSessionHasErrors('birth');

    expect(Patient::count())->toBe(2);
});

test('a pregnancy can be closed as a loss and its details revised while active', function () {
    $pregnancy = Pregnancy::factory()->create(['lmp' => '2026-06-01', 'edd' => '2027-03-08']);

    actingAs(midwife())
        ->patch(route('maternity.update', $pregnancy), ['lmp' => '2026-06-15', 'gravida' => 3, 'para' => 1, 'risk_factors' => ['Anaemia']])
        ->assertSessionHasNoErrors();

    $pregnancy->refresh();

    expect($pregnancy->edd->toDateString())->toBe('2027-03-22')
        ->and($pregnancy->risk_factors)->toBe(['Anaemia']);

    actingAs(midwife(['physician']))
        ->post(route('maternity.close', $pregnancy), ['outcome_note' => 'Incomplete abortion at 9 weeks, evacuated'])
        ->assertSessionHasNoErrors();

    $pregnancy->refresh();

    expect($pregnancy->status)->toBe(PregnancyStatus::Lost)
        ->and($pregnancy->outcome_note)->toContain('abortion');

    actingAs(midwife())
        ->patch(route('maternity.update', $pregnancy), ['lmp' => '2026-06-01'])
        ->assertSessionHasErrors('status');
});

test('the patient profile links to the active pregnancy', function () {
    $pregnancy = Pregnancy::factory()->create();

    actingAs(midwife(['physician']))
        ->get(route('patients.show', $pregnancy->patient_id))
        ->assertInertia(fn ($page) => $page
            ->where('activePregnancy.id', $pregnancy->id)
            ->where('canBookPregnancy', true)
        );
});

test('deliveries feed the NHMIS maternal and child health section and the registers', function () {
    $pregnancy = Pregnancy::factory()->create(['patient_id' => mother()->id]);
    actingAs(midwife())->post(route('maternity.delivery.store', $pregnancy), deliveryPayload(['mode' => 'cs_emergency', 'delivered_at' => '2026-08-20 03:15']));

    $twins = Pregnancy::factory()->create();
    actingAs(midwife())->post(route('maternity.delivery.store', $twins), deliveryPayload([
        'delivered_at' => '2026-08-22 11:00',
        'maternal_outcome' => 'deceased',
        'births' => [['outcome' => 'live', 'sex' => 'F', 'weight_grams' => 2300], ['outcome' => 'live', 'sex' => 'F', 'weight_grams' => 2600]],
    ]));

    $result = app(NhmisReports::class)->maternalChildHealth(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')->endOfDay());
    $row = fn (string $label) => collect($result['rows'])->firstWhere('indicator', $label)['total'];

    expect($row('Deliveries (all)'))->toBe('2')
        ->and($row('Deliveries by caesarean section'))->toBe('1')
        ->and($row('Maternal deaths'))->toBe('1')
        ->and($row('Live births'))->toBe('3')
        ->and($row('Live births — female'))->toBe('2')
        ->and($row('Stillbirths (fresh)'))->toBe('1')
        ->and($row('Low birth weight (< 2.5 kg)'))->toBe('1')
        ->and($row('Breastfed within one hour'))->toBe('1');

    $cmd = midwife(['chief-medical-director']);
    actingAs($cmd)->get(route('reports.run', ['report' => 'delivery-register', 'range' => 'last_month']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows', 2));
    actingAs($cmd)->get(route('reports.run', ['report' => 'birth-register', 'range' => 'last_month']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows', 4));
});

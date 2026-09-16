<?php

use App\Enums\AdmissionStatus;
use App\Enums\DischargeType;
use App\Enums\EncounterStatus;
use App\Enums\EncounterType;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\Immunization;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Problem;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Services\NhmisReports;
use App\Support\NhmisAgeBands;
use Database\Seeders\RolesAndModulesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndModulesSeeder::class);
    Carbon::setTestNow('2026-09-04 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function nhmisFrom(): Carbon
{
    return Carbon::parse('2026-08-01')->startOfDay();
}

function nhmisTo(): Carbon
{
    return Carbon::parse('2026-08-31')->endOfDay();
}

/**
 * A patient of a given sex and age (in years) as at the August 2026 period.
 */
function nhmisPatient(string $sex, int $ageYears): Patient
{
    return Patient::factory()->create([
        'sex' => $sex,
        'date_of_birth' => Carbon::parse('2026-08-15')->subYears($ageYears)->toDateString(),
    ]);
}

function openVisit(Patient $patient, string $openedAt): Visit
{
    return Visit::create([
        'visit_number' => 'V/'.fake()->unique()->numerify('######'),
        'patient_id' => $patient->id,
        'status' => 'open',
        'opened_by' => User::factory()->create()->id,
        'opened_at' => Carbon::parse($openedAt),
    ]);
}

/**
 * A completed consultation with the given coded diagnoses.
 *
 * @param  array<int, array{0: string, 1: string, 2?: string}>  $diagnoses  [name, code, role]
 */
function completedConsultation(Patient $patient, string $at, array $diagnoses = []): Encounter
{
    $encounter = Encounter::create([
        'patient_id' => $patient->id,
        'visit_id' => openVisit($patient, $at)->id,
        'author_id' => User::factory()->create()->id,
        'type' => EncounterType::Consultation,
        'assessment' => 'See coded lines',
        'status' => EncounterStatus::Signed,
        'started_at' => Carbon::parse($at),
        'signed_at' => Carbon::parse($at),
    ]);

    foreach ($diagnoses as [$name, $code, $role]) {
        Problem::factory()->for($patient)->create([
            'encounter_id' => $encounter->id,
            'name' => $name,
            'code' => $code,
            'role' => $role ?? 'primary',
            'status' => 'active',
        ]);
    }

    return $encounter;
}

function rowNamed(array $result, string $column, string $value): array
{
    return collect($result['rows'])->firstWhere($column, $value) ?? [];
}

function summaryValue(array $result, string $label): ?string
{
    return collect($result['summary'])->firstWhere('label', $label)['value'] ?? null;
}

test('age bands follow the NHMIS form', function () {
    $at = Carbon::parse('2026-08-15');

    expect(NhmisAgeBands::bandFor($at->copy()->subDays(10), $at))->toBe('under_28d')
        ->and(NhmisAgeBands::bandFor($at->copy()->subMonths(6), $at))->toBe('under_1y')
        ->and(NhmisAgeBands::bandFor($at->copy()->subYears(3), $at))->toBe('one_to_4')
        ->and(NhmisAgeBands::bandFor($at->copy()->subYears(7), $at))->toBe('five_to_9')
        ->and(NhmisAgeBands::bandFor($at->copy()->subYears(15), $at))->toBe('ten_to_19')
        ->and(NhmisAgeBands::bandFor($at->copy()->subYears(40), $at))->toBe('twenty_plus')
        ->and(NhmisAgeBands::bandFor(null, $at))->toBeNull();
});

test('OPD attendance splits new and repeat visits by sex and age', function () {
    $boy = nhmisPatient('M', 3);
    $woman = nhmisPatient('F', 30);

    openVisit($boy, '2026-08-03 09:00');           // first ever visit → new
    openVisit($woman, '2026-07-20 09:00');         // before the period
    openVisit($woman, '2026-08-10 09:00');         // repeat
    openVisit($woman, '2026-08-25 09:00');         // repeat
    openVisit(nhmisPatient('M', 50), '2026-09-02 09:00'); // after the period

    $result = app(NhmisReports::class)->opdAttendance(nhmisFrom(), nhmisTo());

    expect(rowNamed($result, 'indicator', 'New attendance')['male_u5'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'New attendance')['total'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Repeat attendance')['female_5plus'])->toBe('2')
        ->and(rowNamed($result, 'indicator', 'Total attendance')['total'])->toBe('3')
        ->and(summaryValue($result, 'Patients seen'))->toBe('2');
});

test('morbidity groups coded diagnoses into NHMIS disease lines', function () {
    $child = nhmisPatient('F', 2);
    $man = nhmisPatient('M', 45);

    completedConsultation($child, '2026-08-05 10:00', [['Unspecified malaria', 'B54', 'primary'], ['Anaemia', 'D64.9', 'secondary']]);
    completedConsultation($man, '2026-08-12 10:00', [['Essential hypertension', 'I10', 'primary'], ['Possible TB', 'A16', 'differential']]);
    completedConsultation($man, '2026-08-20 10:00', [['Malaria', 'B50.0', 'primary'], ['Unclear', '', 'secondary'], ['Rare thing', 'Q99.9', 'secondary']]);
    completedConsultation(nhmisPatient('F', 60), '2026-08-22 10:00'); // no diagnosis line
    completedConsultation($man, '2026-09-01 10:00', [['Malaria', 'B54', 'primary']]); // outside period

    $result = app(NhmisReports::class)->morbidity(nhmisFrom(), nhmisTo());

    $malaria = rowNamed($result, 'disease', 'Malaria');
    $htn = rowNamed($result, 'disease', 'Hypertension');

    expect($malaria['total'])->toBe('2')
        ->and($malaria['one_to_4'])->toBe('1')
        ->and($malaria['twenty_plus'])->toBe('1')
        ->and($malaria['female'])->toBe('1')
        ->and($malaria['male'])->toBe('1')
        ->and($malaria['codes'])->toBe('B50–B54')
        ->and($htn['total'])->toBe('1')
        ->and(rowNamed($result, 'disease', 'Anaemia')['total'])->toBe('1')
        ->and(rowNamed($result, 'disease', 'Tuberculosis')['total'])->toBe('0')
        ->and(rowNamed($result, 'disease', 'Other diagnoses (coded)')['total'])->toBe('1')
        ->and(rowNamed($result, 'disease', 'Uncoded diagnoses')['total'])->toBe('1')
        ->and(summaryValue($result, 'Consultations'))->toBe('4')
        ->and(summaryValue($result, 'Coded diagnoses'))->toBe('5')
        ->and(summaryValue($result, 'Uncoded diagnoses'))->toBe('1')
        ->and(summaryValue($result, 'Consultations with no diagnosis line'))->toBe('1');
});

test('in-patient figures count admissions, outcomes, patient-days and occupancy', function () {
    $ward = Ward::factory()->withBeds(10)->create();
    Bed::factory()->outOfService()->create(['ward_id' => $ward->id, 'label' => 'Broken']);
    $child = nhmisPatient('M', 4);
    $woman = nhmisPatient('F', 28);

    // Admitted 1 Aug, discharged home 5 Aug: 5 patient-days.
    Admission::factory()->create([
        'patient_id' => $child->id, 'ward_id' => $ward->id, 'status' => AdmissionStatus::Discharged,
        'admitted_at' => '2026-08-01 08:00', 'discharged_at' => '2026-08-05 12:00', 'discharge_type' => DischargeType::Home,
    ]);
    // Admitted 28 Jul, died 3 Aug: 3 days inside the period.
    Admission::factory()->create([
        'patient_id' => $woman->id, 'ward_id' => $ward->id, 'status' => AdmissionStatus::Discharged,
        'admitted_at' => '2026-07-28 08:00', 'discharged_at' => '2026-08-03 12:00', 'discharge_type' => DischargeType::Deceased,
    ]);
    // Admitted 30 Aug, still on the ward: 2 days inside the period.
    Admission::factory()->create([
        'patient_id' => nhmisPatient('F', 70)->id, 'ward_id' => $ward->id, 'status' => AdmissionStatus::Admitted,
        'admitted_at' => '2026-08-30 08:00',
    ]);

    $result = app(NhmisReports::class)->inpatient(nhmisFrom(), nhmisTo());

    expect(rowNamed($result, 'indicator', 'Admissions')['total'])->toBe('2')
        ->and(rowNamed($result, 'indicator', 'Admissions')['under_5'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Discharges (all)')['total'])->toBe('2')
        ->and(rowNamed($result, 'indicator', 'Deaths')['female'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Discharged home')['male'])->toBe('1')
        ->and(summaryValue($result, 'Patient-days'))->toBe('10')
        ->and(summaryValue($result, 'Usable beds'))->toBe('10')
        ->and(summaryValue($result, 'Bed occupancy'))->toBe('3.2%')
        ->and(summaryValue($result, 'Still admitted at period end'))->toBe('1');
});

test('maternal and child health counts ANC, family planning and immunisation doses', function () {
    $mother = nhmisPatient('F', 26);
    $infant = nhmisPatient('M', 0);
    $toddler = nhmisPatient('F', 2);

    Encounter::factory()->for($mother)->nursing('anc')->signed()->create(['signed_at' => '2026-07-15 10:00']);
    Encounter::factory()->for($mother)->nursing('anc')->signed()->create(['signed_at' => '2026-08-12 10:00']);
    Encounter::factory()->for(nhmisPatient('F', 31))->nursing('anc')->signed()->create(['signed_at' => '2026-08-14 10:00']);
    Encounter::factory()->for(nhmisPatient('F', 35))->nursing('family-planning')->signed()->create(['signed_at' => '2026-08-20 10:00']);
    Encounter::factory()->for($mother)->nursing('anc')->create();
    // A nursing contact is not a consultation and must not count as one.
    Encounter::factory()->for($mother)->nursing('triage')->signed()->create(['signed_at' => '2026-08-13 10:00']);

    Immunization::factory()->for($infant)->create(['vaccine' => 'Penta', 'administered_at' => '2026-08-06 10:00']);
    Immunization::factory()->for($infant)->create(['vaccine' => 'OPV', 'administered_at' => '2026-08-06 10:00']);
    Immunization::factory()->for($toddler)->create(['vaccine' => 'Measles', 'administered_at' => '2026-08-18 10:00']);
    Immunization::factory()->for($toddler)->create(['vaccine' => 'Measles', 'administered_at' => '2026-09-02 10:00']);

    $result = app(NhmisReports::class)->maternalChildHealth(nhmisFrom(), nhmisTo());

    expect(rowNamed($result, 'indicator', 'ANC first visits')['total'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'ANC revisits')['total'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Family planning attendance')['total'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Immunisation — Penta')['under_1'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Immunisation — Measles')['one_plus'])->toBe('1')
        ->and(rowNamed($result, 'indicator', 'Immunisation doses (all antigens)')['total'])->toBe('3')
        ->and(summaryValue($result, 'ANC clients'))->toBe('2')
        ->and(summaryValue($result, 'Children immunised'))->toBe('2');
});

test('laboratory figures count tests and malaria positives', function () {
    $patient = nhmisPatient('M', 20);

    LabResult::factory()->for($patient)->create(['name' => 'Malaria Parasite', 'status' => 'resulted', 'flag' => 'abnormal', 'resulted_at' => '2026-08-02 10:00']);
    LabResult::factory()->for($patient)->create(['name' => 'Malaria Parasite', 'status' => 'resulted', 'flag' => 'normal', 'resulted_at' => '2026-08-09 10:00']);
    LabResult::factory()->for($patient)->create(['name' => 'Full Blood Count', 'status' => 'resulted', 'flag' => 'low', 'resulted_at' => '2026-08-09 10:00']);
    LabResult::factory()->for($patient)->create(['name' => 'Full Blood Count', 'status' => 'pending', 'flag' => null, 'resulted_at' => null]);

    $result = app(NhmisReports::class)->laboratory(nhmisFrom(), nhmisTo());

    expect(rowNamed($result, 'test', 'Malaria Parasite')['performed'])->toBe('2')
        ->and(rowNamed($result, 'test', 'Malaria Parasite')['abnormal'])->toBe('1')
        ->and(summaryValue($result, 'Tests performed'))->toBe('3')
        ->and(summaryValue($result, 'Malaria positive'))->toBe('1');
});

test('the NHMIS reports run from the catalogue with the last-month range', function () {
    $cmd = User::factory()->create();
    $cmd->roles()->sync(Role::where('slug', 'chief-medical-director')->pluck('id'));

    foreach (['nhmis-opd-attendance', 'nhmis-morbidity', 'nhmis-inpatient', 'nhmis-mch', 'nhmis-laboratory'] as $key) {
        actingAs($cmd)
            ->get(route('reports.run', ['report' => $key, 'range' => 'last_month']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.from', '2026-08-01')
                ->where('filters.to', '2026-08-31')
            );
    }
});

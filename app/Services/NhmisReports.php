<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Enums\BirthOutcome;
use App\Enums\DischargeType;
use App\Enums\EncounterType;
use App\Enums\MaternalOutcome;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Birth;
use App\Models\Delivery;
use App\Models\Encounter;
use App\Models\Immunization;
use App\Models\LabResult;
use App\Models\Problem;
use App\Models\Visit;
use App\Support\NhmisAgeBands;
use App\Support\NhmisMorbidity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures behind the NHMIS monthly summary form, each section as a table
 * the report runner can render and export. Counting is done in PHP so the
 * queries stay portable across SQLite and MySQL.
 */
class NhmisReports
{
    /**
     * Section: out-patient attendance, new versus repeat, by sex and age.
     *
     * @return array<string, mixed>
     */
    public function opdAttendance(Carbon $from, Carbon $to): array
    {
        $visits = Visit::query()
            ->whereBetween('opened_at', [$from, $to])
            ->with('patient:id,sex,date_of_birth')
            ->get();

        // A patient's first visit ever is a "new" attendance.
        $firstVisits = Visit::query()
            ->whereIn('patient_id', $visits->pluck('patient_id')->unique())
            ->selectRaw('patient_id, min(id) as first_id')
            ->groupBy('patient_id')
            ->pluck('first_id', 'patient_id');

        $cells = ['new' => $this->emptyAttendanceRow(), 'repeat' => $this->emptyAttendanceRow()];

        foreach ($visits as $visit) {
            $kind = $firstVisits->get($visit->patient_id) === $visit->id ? 'new' : 'repeat';
            $key = $this->attendanceKey($visit->patient?->sex, $visit->patient?->date_of_birth, $visit->opened_at ?? $from);
            $cells[$kind][$key]++;
            $cells[$kind]['total']++;
        }

        $total = $this->emptyAttendanceRow();
        foreach ($total as $key => $_) {
            $total[$key] = $cells['new'][$key] + $cells['repeat'][$key];
        }

        $row = fn (string $label, array $c) => [
            'indicator' => $label,
            'male_u5' => (string) $c['male_u5'],
            'female_u5' => (string) $c['female_u5'],
            'male_5plus' => (string) $c['male_5plus'],
            'female_5plus' => (string) $c['female_5plus'],
            'unknown' => (string) $c['unknown'],
            'total' => (string) $c['total'],
        ];

        return [
            'columns' => [
                ['key' => 'indicator', 'label' => 'Attendance', 'align' => 'left'],
                ['key' => 'male_u5', 'label' => 'Male < 5', 'align' => 'right'],
                ['key' => 'female_u5', 'label' => 'Female < 5', 'align' => 'right'],
                ['key' => 'male_5plus', 'label' => 'Male 5+', 'align' => 'right'],
                ['key' => 'female_5plus', 'label' => 'Female 5+', 'align' => 'right'],
                ['key' => 'unknown', 'label' => 'Age unknown', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
            ],
            'rows' => [
                $row('New attendance', $cells['new']),
                $row('Repeat attendance', $cells['repeat']),
                $row('Total attendance', $total),
            ],
            'summary' => [
                ['label' => 'Visits', 'value' => (string) $visits->count()],
                ['label' => 'Patients seen', 'value' => (string) $visits->pluck('patient_id')->unique()->count()],
            ],
        ];
    }

    /**
     * Section: morbidity, coded diagnoses grouped into NHMIS disease lines by
     * age band and sex.
     *
     * @return array<string, mixed>
     */
    public function morbidity(Carbon $from, Carbon $to): array
    {
        $encounters = Encounter::query()
            ->consultations()
            ->signed()
            ->whereBetween('signed_at', [$from, $to])
            ->with('patient:id,sex,date_of_birth')
            ->get()
            ->keyBy('id');

        $problems = Problem::query()
            ->whereIn('encounter_id', $encounters->keys())
            ->whereIn('role', [Problem::ROLE_PRIMARY, Problem::ROLE_SECONDARY])
            ->get();

        $bandKeys = array_keys(NhmisAgeBands::BANDS);
        $blank = fn () => array_fill_keys([...$bandKeys, 'unknown', 'male', 'female', 'total'], 0);

        $groups = collect(array_keys(NhmisMorbidity::GROUPS))
            ->push(NhmisMorbidity::OTHER, NhmisMorbidity::UNCODED)
            ->mapWithKeys(fn (string $name) => [$name => $blank()])
            ->all();

        $coded = 0;
        $uncoded = 0;

        foreach ($problems as $problem) {
            $encounter = $encounters->get($problem->encounter_id);
            $group = NhmisMorbidity::groupFor($problem->code);

            if ($group === null) {
                $group = trim((string) $problem->code) === '' ? NhmisMorbidity::UNCODED : NhmisMorbidity::OTHER;
            }

            $group === NhmisMorbidity::UNCODED ? $uncoded++ : $coded++;

            $band = NhmisAgeBands::bandFor($encounter?->patient?->date_of_birth, $encounter?->signed_at ?? $from) ?? 'unknown';
            $groups[$group][$band]++;

            match ($encounter?->patient?->sex) {
                'M' => $groups[$group]['male']++,
                'F' => $groups[$group]['female']++,
                default => null,
            };
            $groups[$group]['total']++;
        }

        $rows = collect($groups)->map(fn (array $cells, string $name) => [
            'disease' => $name,
            'codes' => NhmisMorbidity::rangesLabel($name),
            ...collect($cells)->map(fn (int $n) => (string) $n)->all(),
        ])->values()->all();

        $withoutCode = $encounters->keys()->diff($problems->pluck('encounter_id')->unique())->count();

        return [
            'columns' => [
                ['key' => 'disease', 'label' => 'Disease', 'align' => 'left'],
                ['key' => 'codes', 'label' => 'ICD-10', 'align' => 'left'],
                ...collect(NhmisAgeBands::BANDS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'align' => 'right'])->values()->all(),
                ['key' => 'unknown', 'label' => 'Age unknown', 'align' => 'right'],
                ['key' => 'male', 'label' => 'Male', 'align' => 'right'],
                ['key' => 'female', 'label' => 'Female', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Consultations', 'value' => (string) $encounters->count()],
                ['label' => 'Coded diagnoses', 'value' => (string) $coded],
                ['label' => 'Uncoded diagnoses', 'value' => (string) $uncoded],
                ['label' => 'Consultations with no diagnosis line', 'value' => (string) $withoutCode],
            ],
        ];
    }

    /**
     * Section: in-patient admissions, discharges and deaths, with bed use.
     *
     * @return array<string, mixed>
     */
    public function inpatient(Carbon $from, Carbon $to): array
    {
        $admitted = Admission::query()
            ->whereBetween('admitted_at', [$from, $to])
            ->with('patient:id,sex,date_of_birth')
            ->get();

        $discharged = Admission::query()
            ->whereBetween('discharged_at', [$from, $to])
            ->with('patient:id,sex,date_of_birth')
            ->get();

        $counts = fn (Collection $set, CarbonInterface $at) => $this->splitBySexAndAge($set, $at);

        $rows = [
            $this->inpatientRow('Admissions', $counts($admitted, $from)),
            $this->inpatientRow('Discharges (all)', $counts($discharged, $to)),
            $this->inpatientRow('Discharged home', $counts($discharged->where('discharge_type', DischargeType::Home), $to)),
            $this->inpatientRow('Referred / transferred out', $counts($discharged->where('discharge_type', DischargeType::Referred), $to)),
            $this->inpatientRow('Discharged against medical advice', $counts($discharged->where('discharge_type', DischargeType::Dama), $to)),
            $this->inpatientRow('Absconded', $counts($discharged->where('discharge_type', DischargeType::Absconded), $to)),
            $this->inpatientRow('Deaths', $counts($discharged->where('discharge_type', DischargeType::Deceased), $to)),
        ];

        // Patient-days: every admission overlapping the period contributes the
        // days it spent on the ward inside the period.
        $overlapping = Admission::query()
            ->whereNotNull('admitted_at')
            ->where('admitted_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('discharged_at')->orWhere('discharged_at', '>=', $from))
            ->get();

        $patientDays = $overlapping->sum(function (Admission $a) use ($from, $to) {
            $start = $a->admitted_at->copy()->startOfDay()->max($from->copy()->startOfDay());
            $end = ($a->discharged_at ?? now())->copy()->startOfDay()->min($to->copy()->startOfDay());

            return $start->greaterThan($end) ? 0 : (int) $start->diffInDays($end) + 1;
        });

        $beds = Bed::query()->where('status', '!=', BedStatus::OutOfService->value)->count();
        $daysInPeriod = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $available = $beds * $daysInPeriod;

        $stays = $discharged->map(fn (Admission $a) => $a->lengthOfStayDays())->filter();

        return [
            'columns' => [
                ['key' => 'indicator', 'label' => 'Indicator', 'align' => 'left'],
                ['key' => 'male', 'label' => 'Male', 'align' => 'right'],
                ['key' => 'female', 'label' => 'Female', 'align' => 'right'],
                ['key' => 'under_5', 'label' => '< 5 years', 'align' => 'right'],
                ['key' => 'five_plus', 'label' => '5 years +', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Still admitted at period end', 'value' => (string) Admission::where('status', AdmissionStatus::Admitted->value)->count()],
                ['label' => 'Patient-days', 'value' => (string) $patientDays],
                ['label' => 'Usable beds', 'value' => (string) $beds],
                ['label' => 'Bed occupancy', 'value' => $available > 0 ? number_format($patientDays / $available * 100, 1).'%' : '—'],
                ['label' => 'Avg. length of stay (days)', 'value' => $stays->isEmpty() ? '—' : number_format($stays->avg(), 1)],
            ],
        ];
    }

    /**
     * Section: maternal and child health — antenatal care, family planning
     * and immunisation.
     *
     * @return array<string, mixed>
     */
    public function maternalChildHealth(Carbon $from, Carbon $to): array
    {
        $notes = Encounter::query()
            ->ofType(EncounterType::Nursing)
            ->signed()
            ->whereBetween('signed_at', [$from, $to])
            ->whereHas('servicePoint', fn ($q) => $q->whereIn('slug', ['anc', 'family-planning']))
            ->with('servicePoint:id,slug')
            ->get();

        $anc = $notes->filter(fn (Encounter $e) => $e->servicePoint?->slug === 'anc');
        $fp = $notes->filter(fn (Encounter $e) => $e->servicePoint?->slug === 'family-planning');

        // A woman's first ANC encounter ever counts as a first visit.
        $firstAnc = Encounter::query()
            ->ofType(EncounterType::Nursing)
            ->atServicePoint('anc')
            ->signed()
            ->whereIn('patient_id', $anc->pluck('patient_id')->unique())
            ->selectRaw('patient_id, min(id) as first_id')
            ->groupBy('patient_id')
            ->pluck('first_id', 'patient_id');

        $ancFirst = $anc->filter(fn (Encounter $e) => $firstAnc->get($e->patient_id) === $e->id)->count();

        $immunizations = Immunization::query()
            ->whereBetween('administered_at', [$from, $to])
            ->with('patient:id,sex,date_of_birth')
            ->get();

        $immRow = function (string $label, Collection $doses) {
            $underOne = $doses->filter(fn (Immunization $i) => NhmisAgeBands::isUnderOne($i->patient?->date_of_birth, $i->administered_at ?? now()) === true)->count();

            return [
                'indicator' => $label,
                'under_1' => (string) $underOne,
                'one_plus' => (string) ($doses->count() - $underOne),
                'total' => (string) $doses->count(),
            ];
        };

        $deliveries = Delivery::query()->whereBetween('delivered_at', [$from, $to])->with('births')->get();
        $births = $deliveries->flatMap(fn (Delivery $d) => $d->births);
        $liveBirths = $births->filter(fn (Birth $b) => $b->outcome->isLive());

        $adult = fn (string $label, int $n) => ['indicator' => $label, 'under_1' => '—', 'one_plus' => (string) $n, 'total' => (string) $n];
        $baby = fn (string $label, int $n) => ['indicator' => $label, 'under_1' => (string) $n, 'one_plus' => '—', 'total' => (string) $n];

        $rows = [
            $adult('ANC first visits', $ancFirst),
            $adult('ANC revisits', $anc->count() - $ancFirst),
            $adult('ANC attendance (all)', $anc->count()),
            $adult('Family planning attendance', $fp->count()),
            $adult('Deliveries (all)', $deliveries->count()),
            $adult('Deliveries by caesarean section', $deliveries->filter(fn (Delivery $d) => $d->mode->isCaesarean())->count()),
            $adult('Deliveries attended by skilled staff', $deliveries->filter(fn (Delivery $d) => $d->attendant_id !== null && $d->place === Delivery::PLACE_FACILITY)->count()),
            $adult('Maternal deaths', $deliveries->where('maternal_outcome', MaternalOutcome::Deceased)->count()),
            $baby('Live births', $liveBirths->count()),
            $baby('Live births — male', $liveBirths->where('sex', 'M')->count()),
            $baby('Live births — female', $liveBirths->where('sex', 'F')->count()),
            $baby('Stillbirths (fresh)', $births->where('outcome', BirthOutcome::StillbirthFresh)->count()),
            $baby('Stillbirths (macerated)', $births->where('outcome', BirthOutcome::StillbirthMacerated)->count()),
            $baby('Low birth weight (< 2.5 kg)', $liveBirths->filter(fn (Birth $b) => $b->isLowBirthWeight())->count()),
            $baby('Breastfed within one hour', $liveBirths->where('breastfed_within_hour', true)->count()),
            $baby('Newborns given BCG at birth', $liveBirths->where('bcg_given', true)->count()),
        ];

        foreach ($immunizations->groupBy('vaccine')->sortKeys() as $vaccine => $doses) {
            $rows[] = $immRow("Immunisation — {$vaccine}", $doses);
        }

        $rows[] = $immRow('Immunisation doses (all antigens)', $immunizations);

        return [
            'columns' => [
                ['key' => 'indicator', 'label' => 'Indicator', 'align' => 'left'],
                ['key' => 'under_1', 'label' => '< 1 year', 'align' => 'right'],
                ['key' => 'one_plus', 'label' => '1 year +', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'ANC clients', 'value' => (string) $anc->pluck('patient_id')->unique()->count()],
                ['label' => 'Children immunised', 'value' => (string) $immunizations->pluck('patient_id')->unique()->count()],
            ],
        ];
    }

    /**
     * Section: laboratory tests performed and abnormal results, with the
     * malaria testing figures the form asks for.
     *
     * @return array<string, mixed>
     */
    public function laboratory(Carbon $from, Carbon $to): array
    {
        $results = LabResult::query()
            ->where('status', LabResult::STATUS_RESULTED)
            ->whereBetween('resulted_at', [$from, $to])
            ->get();

        $abnormal = fn (LabResult $r) => in_array($r->flag, ['abnormal', 'high', 'low', 'critical'], true);

        $rows = $results
            ->groupBy('name')
            ->sortKeys()
            ->map(fn (Collection $group, string $name) => [
                'test' => $name,
                'performed' => (string) $group->count(),
                'abnormal' => (string) $group->filter($abnormal)->count(),
            ])
            ->values()
            ->all();

        $malaria = $results->filter(fn (LabResult $r) => str_contains(strtolower($r->name), 'malaria'));

        return [
            'columns' => [
                ['key' => 'test', 'label' => 'Test', 'align' => 'left'],
                ['key' => 'performed', 'label' => 'Performed', 'align' => 'right'],
                ['key' => 'abnormal', 'label' => 'Abnormal / positive', 'align' => 'right'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Tests performed', 'value' => (string) $results->count()],
                ['label' => 'Malaria tests', 'value' => (string) $malaria->count()],
                ['label' => 'Malaria positive', 'value' => (string) $malaria->filter($abnormal)->count()],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyAttendanceRow(): array
    {
        return ['male_u5' => 0, 'female_u5' => 0, 'male_5plus' => 0, 'female_5plus' => 0, 'unknown' => 0, 'total' => 0];
    }

    private function attendanceKey(?string $sex, ?CarbonInterface $dateOfBirth, CarbonInterface $at): string
    {
        $underFive = NhmisAgeBands::isUnderFive($dateOfBirth, $at);

        if ($underFive === null || ! in_array($sex, ['M', 'F'], true)) {
            return 'unknown';
        }

        return ($sex === 'M' ? 'male' : 'female').($underFive ? '_u5' : '_5plus');
    }

    /**
     * @param  Collection<int, Admission>  $set
     * @return array{male: int, female: int, under_5: int, five_plus: int, total: int}
     */
    private function splitBySexAndAge(Collection $set, CarbonInterface $at): array
    {
        $out = ['male' => 0, 'female' => 0, 'under_5' => 0, 'five_plus' => 0, 'total' => 0];

        foreach ($set as $admission) {
            $patient = $admission->patient;
            $out['total']++;

            match ($patient?->sex) {
                'M' => $out['male']++,
                'F' => $out['female']++,
                default => null,
            };

            match (NhmisAgeBands::isUnderFive($patient?->date_of_birth, $admission->admitted_at ?? $at)) {
                true => $out['under_5']++,
                false => $out['five_plus']++,
                default => null,
            };
        }

        return $out;
    }

    /**
     * @param  array{male: int, female: int, under_5: int, five_plus: int, total: int}  $c
     * @return array<string, string>
     */
    private function inpatientRow(string $label, array $c): array
    {
        return [
            'indicator' => $label,
            'male' => (string) $c['male'],
            'female' => (string) $c['female'],
            'under_5' => (string) $c['under_5'],
            'five_plus' => (string) $c['five_plus'],
            'total' => (string) $c['total'],
        ];
    }
}

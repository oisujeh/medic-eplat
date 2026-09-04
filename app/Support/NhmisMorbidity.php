<?php

namespace App\Support;

use App\Models\IcdCode;

/**
 * The disease groups on the NHMIS monthly summary, each defined as a set of
 * ICD-10 category ranges. A coded diagnosis lands in the first group whose
 * ranges contain its three-character category.
 */
class NhmisMorbidity
{
    /**
     * Groups in form order: name => list of [from, to] category ranges
     * (inclusive; a single category is [X, X]).
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    public const GROUPS = [
        'Malaria' => [['B50', 'B54']],
        'Malaria in pregnancy' => [['O98', 'O98']],
        'Typhoid fever' => [['A01', 'A01']],
        'Cholera' => [['A00', 'A00']],
        'Diarrhoea (with or without dehydration)' => [['A02', 'A09']],
        'Dysentery' => [['A03', 'A03'], ['A06', 'A06']],
        'Pneumonia' => [['J12', 'J18']],
        'Other acute respiratory infection' => [['J00', 'J06'], ['J09', 'J11'], ['J20', 'J22']],
        'Asthma' => [['J45', 'J46']],
        'Tuberculosis' => [['A15', 'A19']],
        'HIV / AIDS' => [['B20', 'B24']],
        'Sexually transmitted infections' => [['A50', 'A64']],
        'Measles' => [['B05', 'B05']],
        'Meningitis' => [['A39', 'A39'], ['G00', 'G03']],
        'Tetanus' => [['A33', 'A35']],
        'Yellow fever' => [['A95', 'A95']],
        'Lassa fever / viral haemorrhagic fever' => [['A96', 'A99']],
        'Hepatitis' => [['B15', 'B19']],
        'Schistosomiasis' => [['B65', 'B65']],
        'Intestinal worms' => [['B76', 'B83']],
        'Skin infections' => [['B35', 'B36'], ['B86', 'B86'], ['L00', 'L08']],
        'Malnutrition' => [['E40', 'E46']],
        'Anaemia' => [['D50', 'D64']],
        'Sickle cell disease' => [['D57', 'D57']],
        'Hypertension' => [['I10', 'I15']],
        'Diabetes mellitus' => [['E10', 'E14']],
        'Stroke' => [['I60', 'I64']],
        'Heart disease' => [['I20', 'I52']],
        'Mental disorders' => [['F00', 'F99']],
        'Epilepsy' => [['G40', 'G41']],
        'Eye diseases' => [['H00', 'H59']],
        'Ear diseases' => [['H60', 'H95']],
        'Dental conditions' => [['K00', 'K14']],
        'Peptic ulcer disease' => [['K25', 'K30']],
        'Urinary tract infection' => [['N10', 'N12'], ['N30', 'N30'], ['N39', 'N39']],
        'Pregnancy complications' => [['O00', 'O97'], ['O99', 'O99']],
        'Neonatal conditions' => [['P00', 'P96']],
        'Road traffic accidents' => [['V01', 'V89']],
        'Injuries and burns' => [['S00', 'T35']],
        'Snake bite and poisoning' => [['T36', 'T65']],
        'Neoplasms' => [['C00', 'D48']],
        // Catch-alls, after the specific lines above.
        'Other infectious and parasitic diseases' => [['A00', 'B99']],
        'Other endocrine and nutritional conditions' => [['E00', 'E90']],
        'Other circulatory conditions' => [['I00', 'I99']],
        'Other respiratory conditions' => [['J30', 'J99']],
        'Other digestive conditions' => [['K15', 'K93']],
        'Other skin conditions' => [['L10', 'L99']],
        'Musculoskeletal conditions' => [['M00', 'M99']],
        'Other genitourinary conditions' => [['N00', 'N99']],
        'Other nervous system conditions' => [['G04', 'G99']],
        'Other injuries and external causes' => [['S00', 'Y98']],
    ];

    public const OTHER = 'Other diagnoses (coded)';

    public const UNCODED = 'Uncoded diagnoses';

    /**
     * The NHMIS group for an ICD-10 code, or null when it belongs to none.
     */
    public static function groupFor(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $category = IcdCode::categoryOf($code);

        if (! preg_match('/^[A-Z]\d{2}$/', $category)) {
            return null;
        }

        foreach (self::GROUPS as $name => $ranges) {
            foreach ($ranges as [$from, $to]) {
                if (strcmp($category, $from) >= 0 && strcmp($category, $to) <= 0) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The category ranges of a group as a display string, e.g. "B50–B54".
     */
    public static function rangesLabel(string $group): string
    {
        return collect(self::GROUPS[$group] ?? [])
            ->map(fn (array $range) => $range[0] === $range[1] ? $range[0] : "{$range[0]}–{$range[1]}")
            ->implode(', ');
    }
}

<?php

namespace Database\Seeders;

use App\Enums\LabDepartment;
use App\Models\LabTest;
use Illuminate\Database\Seeder;

class LabCompendiumSeeder extends Seeder
{
    /**
     * Individual analytes and standalone tests.
     * [code, name, department, specimen, unit, low, high, text, price, tat]
     *
     * @var array<int, array{0:string,1:string,2:LabDepartment,3:?string,4:?string,5:?float,6:?float,7:?string,8:?float,9:?int}>
     */
    protected array $tests = [
        // Haematology — FBC components
        ['HB', 'Haemoglobin', LabDepartment::Haematology, 'EDTA blood', 'g/dL', 11.0, 17.0, null, 450, null],
        ['WBC', 'White Cell Count', LabDepartment::Haematology, 'EDTA blood', '×10⁹/L', 4.0, 11.0, null, 450, null],
        ['PLT', 'Platelet Count', LabDepartment::Haematology, 'EDTA blood', '×10⁹/L', 150.0, 400.0, null, 450, null],
        ['PCV', 'Packed Cell Volume', LabDepartment::Haematology, 'EDTA blood', '%', 36.0, 54.0, null, 400, null],
        ['NEUT', 'Neutrophils', LabDepartment::Haematology, 'EDTA blood', '%', 40.0, 75.0, null, 400, null],
        ['LYMPH', 'Lymphocytes', LabDepartment::Haematology, 'EDTA blood', '%', 20.0, 45.0, null, 400, null],
        // U&E components
        ['NA', 'Sodium', LabDepartment::Chemistry, 'Serum', 'mmol/L', 135.0, 145.0, null, 450, null],
        ['K', 'Potassium', LabDepartment::Chemistry, 'Serum', 'mmol/L', 3.5, 5.1, null, 450, null],
        ['CL', 'Chloride', LabDepartment::Chemistry, 'Serum', 'mmol/L', 98.0, 107.0, null, 400, null],
        ['UREA', 'Urea', LabDepartment::Chemistry, 'Serum', 'mmol/L', 2.5, 7.1, null, 450, null],
        ['CREA', 'Creatinine', LabDepartment::Chemistry, 'Serum', 'µmol/L', 62.0, 106.0, null, 450, null],
        // LFT components
        ['ALT', 'ALT', LabDepartment::Chemistry, 'Serum', 'U/L', 7.0, 56.0, null, 500, null],
        ['AST', 'AST', LabDepartment::Chemistry, 'Serum', 'U/L', 10.0, 40.0, null, 500, null],
        ['ALP', 'Alkaline Phosphatase', LabDepartment::Chemistry, 'Serum', 'U/L', 44.0, 147.0, null, 500, null],
        ['TBIL', 'Total Bilirubin', LabDepartment::Chemistry, 'Serum', 'µmol/L', 5.0, 21.0, null, 500, null],
        ['ALB', 'Albumin', LabDepartment::Chemistry, 'Serum', 'g/L', 35.0, 50.0, null, 500, null],
        // Standalone chemistry
        ['FBS', 'Fasting Blood Sugar', LabDepartment::Chemistry, 'Fluoride plasma', 'mmol/L', 3.9, 5.5, null, 1500, 2],
        ['RBS', 'Random Blood Sugar', LabDepartment::Chemistry, 'Fluoride plasma', 'mmol/L', 3.9, 7.8, null, 1500, 2],
        ['HBA1C', 'HbA1c', LabDepartment::Chemistry, 'EDTA blood', '%', null, 5.7, null, 6000, 24],
        // Haematology standalone
        ['ESR', 'ESR', LabDepartment::Haematology, 'EDTA blood', 'mm/hr', 0.0, 20.0, null, 1500, 2],
        ['INR', 'Prothrombin Time (INR)', LabDepartment::Haematology, 'Citrate plasma', 'INR', 0.8, 1.2, null, 3000, 4],
        ['BLGP', 'Blood Group (ABO & Rh)', LabDepartment::Haematology, 'EDTA blood', null, null, null, 'Report ABO group and Rh(D)', 2000, 2],
        // Microbiology / Serology / Molecular / Immunology
        ['MP', 'Malaria Parasite', LabDepartment::Microbiology, 'EDTA blood', null, null, null, 'Negative', 1500, 2],
        ['WIDAL', 'Widal Test', LabDepartment::Serology, 'Serum', null, null, null, 'Negative (< 1:80)', 2500, 4],
        ['HIV', 'HIV Screening', LabDepartment::Serology, 'Serum / whole blood', null, null, null, 'Non-reactive', 2000, 2],
        ['HBSAG', 'Hepatitis B (HBsAg)', LabDepartment::Serology, 'Serum', null, null, null, 'Non-reactive', 2500, 4],
        ['HCV', 'Hepatitis C Antibody', LabDepartment::Serology, 'Serum', null, null, null, 'Non-reactive', 3000, 4],
        ['VDRL', 'VDRL (Syphilis)', LabDepartment::Serology, 'Serum', null, null, null, 'Non-reactive', 2000, 4],
        ['VL', 'HIV Viral Load', LabDepartment::Molecular, 'Plasma (PPT)', 'copies/mL', null, 50.0, '< 50 (Undetectable)', 25000, 72],
        ['CD4', 'CD4 Count', LabDepartment::Immunology, 'EDTA blood', 'cells/mm³', 500.0, 1500.0, null, 8000, 24],
        ['PSA', 'Prostate Specific Antigen', LabDepartment::Immunology, 'Serum', 'ng/mL', 0.0, 4.0, null, 6000, 24],
        ['TSH', 'Thyroid Stimulating Hormone', LabDepartment::Immunology, 'Serum', 'mIU/L', 0.4, 4.0, null, 6000, 24],
        ['URIN', 'Urinalysis', LabDepartment::Urinalysis, 'Urine', null, null, null, 'NAD (No abnormality detected)', 1500, 2],
    ];

    /**
     * Panels and the analyte codes they expand into.
     * code => [name, department, specimen, price, tat, [component codes]]
     *
     * @var array<string, array{0:string,1:LabDepartment,2:string,3:float,4:int,5:array<int,string>}>
     */
    protected array $panels = [
        'FBC' => ['Full Blood Count', LabDepartment::Haematology, 'EDTA blood', 2500, 4, ['HB', 'WBC', 'PLT', 'PCV', 'NEUT', 'LYMPH']],
        'UE' => ['Urea, Electrolytes & Creatinine', LabDepartment::Chemistry, 'Serum', 4000, 6, ['NA', 'K', 'CL', 'UREA', 'CREA']],
        'LFT' => ['Liver Function Test', LabDepartment::Chemistry, 'Serum', 5000, 6, ['ALT', 'AST', 'ALP', 'TBIL', 'ALB']],
    ];

    /**
     * Seed the orderable laboratory test compendium.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->tests as [$code, $name, $dept, $specimen, $unit, $low, $high, $text, $price, $tat]) {
            LabTest::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'department' => $dept,
                    'specimen_type' => $specimen,
                    'unit' => $unit,
                    'reference_low' => $low,
                    'reference_high' => $high,
                    'reference_text' => $text,
                    'price' => $price,
                    'turnaround_hours' => $tat,
                    'is_panel' => false,
                    'is_active' => true,
                    'sort_order' => $sort += 10,
                ],
            );
        }

        foreach ($this->panels as $code => [$name, $dept, $specimen, $price, $tat, $components]) {
            $panel = LabTest::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'department' => $dept,
                    'specimen_type' => $specimen,
                    'price' => $price,
                    'turnaround_hours' => $tat,
                    'is_panel' => true,
                    'is_active' => true,
                    'sort_order' => $sort += 10,
                ],
            );

            $sync = LabTest::whereIn('code', $components)->pluck('id', 'code');
            $panel->components()->sync(
                collect($components)
                    ->mapWithKeys(fn (string $c, int $i) => [$sync[$c] => ['sort_order' => $i]])
                    ->all(),
            );
        }
    }
}

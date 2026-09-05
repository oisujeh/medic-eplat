<?php

namespace Database\Seeders;

use App\Enums\ConceptClass;
use App\Enums\ObservationCode;
use App\Models\ConceptSource;
use App\Models\InventoryItem;
use App\Models\LabTest;
use App\Services\Terminology;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The starting terminology: the coding systems, the observation registry
 * with its LOINC codes, the laboratory compendium mapped where the code is
 * known, and the drug, allergen and vaccine dictionaries. Idempotent, and
 * additive: a facility's own additions survive a reseed.
 */
class TerminologySeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string|null, 3: string|null}> code, name, version, url
     */
    protected array $sources = [
        ['LOCAL', 'Facility local codes', null, null],
        ['ICD10', 'ICD-10 (WHO)', '2019', 'https://icd.who.int/browse10/2019/en'],
        ['LOINC', 'LOINC', '2.78', 'https://loinc.org'],
        ['NEML', 'Nigeria Essential Medicines List', '2020', 'https://health.gov.ng'],
    ];

    /**
     * LOINC codes for the observation registry.
     *
     * @var array<string, string>
     */
    protected array $observationLoinc = [
        'temperature' => '8310-5',
        'systolic_bp' => '8480-6',
        'diastolic_bp' => '8462-4',
        'pulse' => '8867-4',
        'respiratory_rate' => '9279-1',
        'spo2' => '59408-5',
        'blood_glucose' => '2339-0',
        'pain_score' => '72514-3',
        'weight' => '29463-7',
        'height' => '8302-2',
        'bmi' => '39156-5',
        'muac' => '56072-2',
        'head_circumference' => '9843-4',
        'gestational_age' => '11884-4',
        'fundal_height' => '11881-0',
        'fetal_heart_rate' => '55283-6',
        'presentation' => '11875-2',
    ];

    /**
     * LOINC codes for the laboratory compendium, by local test code. Tests
     * not listed are registered as concepts without a LOINC mapping.
     *
     * @var array<string, string>
     */
    protected array $labLoinc = [
        'HB' => '718-7',
        'WBC' => '6690-2',
        'PLT' => '777-3',
        'PCV' => '4544-3',
        'NEUT' => '770-8',
        'LYMPH' => '736-9',
        'NA' => '2951-2',
        'K' => '2823-3',
        'CL' => '2075-0',
        'UREA' => '3094-0',
        'CREA' => '2160-0',
        'ALT' => '1742-6',
        'AST' => '1920-8',
        'ALP' => '6768-6',
        'TBIL' => '1975-2',
        'ALB' => '1751-7',
        'FBS' => '1558-6',
        'RBS' => '2345-7',
        'HBA1C' => '4548-4',
        'ESR' => '30341-2',
        'INR' => '6301-6',
        'BLGP' => '882-1',
        'MP' => '32700-7',
        'HIV' => '75622-1',
        'HBSAG' => '5195-3',
        'HCV' => '13955-0',
        'VDRL' => '20507-0',
        'VL' => '20447-9',
        'CD4' => '24467-3',
        'PSA' => '2857-1',
        'TSH' => '3016-3',
        'URIN' => '24356-8',
        'FBC' => '58410-2',
        'UE' => '24362-6',
        'LFT' => '24325-3',
    ];

    /**
     * Drugs from the essential medicines list a general hospital uses daily.
     * [name, synonyms]
     *
     * @var array<int, array{0: string, 1: list<string>}>
     */
    protected array $drugs = [
        ['Paracetamol', ['Acetaminophen', 'Panadol', 'Emzor Paracetamol']],
        ['Ibuprofen', ['Brufen']],
        ['Diclofenac', ['Voltaren']],
        ['Aspirin', ['Acetylsalicylic acid']],
        ['Tramadol', []],
        ['Morphine', []],
        ['Amoxicillin', ['Amoxil']],
        ['Amoxicillin/Clavulanate', ['Co-amoxiclav', 'Augmentin']],
        ['Ampicillin/Cloxacillin', ['Ampiclox']],
        ['Benzylpenicillin', ['Penicillin G']],
        ['Ceftriaxone', ['Rocephin']],
        ['Cefuroxime', ['Zinnat']],
        ['Ciprofloxacin', ['Ciprotab']],
        ['Levofloxacin', []],
        ['Azithromycin', ['Zithromax']],
        ['Erythromycin', []],
        ['Metronidazole', ['Flagyl']],
        ['Doxycycline', []],
        ['Gentamicin', []],
        ['Cotrimoxazole', ['Septrin', 'Trimethoprim/Sulfamethoxazole']],
        ['Nitrofurantoin', []],
        ['Fluconazole', []],
        ['Nystatin', []],
        ['Clotrimazole', []],
        ['Albendazole', []],
        ['Mebendazole', []],
        ['Praziquantel', []],
        ['Ivermectin', []],
        ['Artemether/Lumefantrine', ['Coartem', 'Lonart', 'AL']],
        ['Artesunate', []],
        ['Artesunate/Amodiaquine', ['ASAQ']],
        ['Dihydroartemisinin/Piperaquine', ['P-Alaxin', 'DHP']],
        ['Sulfadoxine/Pyrimethamine', ['Fansidar', 'SP']],
        ['Quinine', []],
        ['Amlodipine', ['Norvasc']],
        ['Nifedipine', ['Adalat']],
        ['Lisinopril', []],
        ['Losartan', []],
        ['Hydrochlorothiazide', ['HCTZ']],
        ['Furosemide', ['Lasix', 'Frusemide']],
        ['Spironolactone', []],
        ['Atenolol', []],
        ['Methyldopa', ['Aldomet']],
        ['Labetalol', []],
        ['Atorvastatin', []],
        ['Digoxin', []],
        ['Metformin', ['Glucophage']],
        ['Glibenclamide', ['Daonil']],
        ['Glimepiride', []],
        ['Insulin (soluble)', ['Regular insulin', 'Actrapid']],
        ['Insulin (isophane)', ['NPH insulin', 'Insulatard']],
        ['Omeprazole', []],
        ['Ranitidine', []],
        ['Magnesium trisilicate', ['Antacid']],
        ['Metoclopramide', []],
        ['Promethazine', ['Phenergan']],
        ['Ondansetron', []],
        ['Hyoscine butylbromide', ['Buscopan']],
        ['Oral rehydration salts', ['ORS']],
        ['Zinc sulphate', ['Zinc']],
        ['Loperamide', []],
        ['Salbutamol', ['Ventolin']],
        ['Aminophylline', []],
        ['Prednisolone', []],
        ['Hydrocortisone', []],
        ['Dexamethasone', []],
        ['Chlorpheniramine', ['Piriton']],
        ['Cetirizine', []],
        ['Loratadine', []],
        ['Adrenaline', ['Epinephrine']],
        ['Diazepam', ['Valium']],
        ['Phenobarbital', ['Phenobarbitone']],
        ['Carbamazepine', ['Tegretol']],
        ['Sodium valproate', ['Epilim']],
        ['Phenytoin', []],
        ['Haloperidol', []],
        ['Chlorpromazine', ['Largactil']],
        ['Amitriptyline', []],
        ['Fluoxetine', []],
        ['Ferrous sulphate', ['Iron tablets']],
        ['Folic acid', []],
        ['Ferrous sulphate/Folic acid', ['Fefol', 'Iron and folate']],
        ['Vitamin A', ['Retinol']],
        ['Multivitamin', []],
        ['Vitamin B complex', []],
        ['Calcium', []],
        ['Oxytocin', []],
        ['Misoprostol', ['Cytotec']],
        ['Ergometrine', []],
        ['Magnesium sulphate', ['MgSO4']],
        ['Tetanus immunoglobulin', ['TIG']],
        ['Anti-snake venom', ['ASV']],
        ['Anti-rabies vaccine', ['ARV (rabies)']],
        ['Normal saline 0.9%', ['Sodium chloride 0.9%', 'NaCl']],
        ['Dextrose 5%', ['D5W']],
        ['Dextrose 10%', ['D10W']],
        ['Ringer\'s lactate', ['Hartmann\'s solution']],
        ['Lignocaine', ['Lidocaine', 'Xylocaine']],
        ['Ketamine', []],
        ['Chloramphenicol eye drops', []],
        ['Tetracycline eye ointment', []],
        ['Gentian violet', ['GV paint']],
        ['Benzyl benzoate', []],
        ['Permethrin', []],
        ['Zidovudine/Lamivudine', ['AZT/3TC']],
        ['Tenofovir/Lamivudine/Dolutegravir', ['TLD']],
        ['Nevirapine', []],
        ['Isoniazid', ['INH']],
        ['Rifampicin/Isoniazid/Pyrazinamide/Ethambutol', ['RHZE']],
        ['Rifampicin/Isoniazid', ['RH']],
    ];

    /**
     * [name, synonyms]
     *
     * @var array<int, array{0: string, 1: list<string>}>
     */
    protected array $allergens = [
        ['Penicillins', ['Penicillin', 'Amoxicillin', 'Ampicillin', 'Augmentin']],
        ['Cephalosporins', ['Ceftriaxone', 'Cefuroxime']],
        ['Sulphonamides', ['Sulfonamides', 'Septrin', 'Cotrimoxazole', 'Fansidar']],
        ['NSAIDs', ['Ibuprofen', 'Diclofenac', 'Non-steroidal anti-inflammatory drugs']],
        ['Aspirin', ['Acetylsalicylic acid']],
        ['Paracetamol', ['Acetaminophen']],
        ['Quinolones', ['Ciprofloxacin', 'Levofloxacin']],
        ['Macrolides', ['Erythromycin', 'Azithromycin']],
        ['Metronidazole', ['Flagyl']],
        ['Tetracyclines', ['Doxycycline', 'Tetracycline']],
        ['Artemisinin combinations', ['Coartem', 'ACT']],
        ['Quinine', []],
        ['Chloroquine', []],
        ['Opioids', ['Morphine', 'Tramadol', 'Codeine']],
        ['Local anaesthetics', ['Lignocaine', 'Lidocaine']],
        ['Iodine contrast', ['Contrast media']],
        ['Latex', []],
        ['Adhesive tape / plaster', ['Plaster']],
        ['Peanuts', ['Groundnut']],
        ['Eggs', []],
        ['Milk', ['Dairy', 'Lactose']],
        ['Fish', []],
        ['Shellfish', ['Crayfish', 'Prawn', 'Shrimp']],
        ['Soy', ['Soya']],
        ['Wheat', ['Gluten']],
        ['Beans', []],
        ['Dust', ['House dust mite']],
        ['Pollen', []],
        ['Animal dander', ['Cat', 'Dog']],
        ['Insect sting', ['Bee sting', 'Wasp sting']],
        ['Cold', []],
        ['Sunlight', []],
    ];

    /**
     * The routine immunization schedule and other vaccines given here.
     * [name, synonyms]
     *
     * @var array<int, array{0: string, 1: list<string>}>
     */
    protected array $vaccines = [
        ['BCG', ['Bacille Calmette-Guerin']],
        ['Hepatitis B (birth dose)', ['HepB0', 'HepB birth']],
        ['OPV', ['Oral polio vaccine', 'bOPV']],
        ['IPV', ['Inactivated polio vaccine']],
        ['Pentavalent (DTP-HepB-Hib)', ['Penta', 'DPT-HepB-Hib']],
        ['PCV', ['Pneumococcal conjugate vaccine']],
        ['Rotavirus', ['Rota']],
        ['Measles', ['MCV', 'Measles vaccine']],
        ['Measles-Rubella', ['MR']],
        ['Yellow fever', ['YF']],
        ['Meningitis A', ['MenA', 'MenAfriVac']],
        ['HPV', ['Human papillomavirus vaccine']],
        ['Tetanus-diphtheria', ['Td', 'TT', 'Tetanus toxoid']],
        ['Malaria (R21)', ['R21/Matrix-M', 'Malaria vaccine']],
        ['COVID-19', ['Coronavirus vaccine']],
        ['Rabies', ['Anti-rabies vaccine']],
        ['Vitamin A supplement', []],
    ];

    public function run(): void
    {
        foreach ($this->sources as [$code, $name, $version, $url]) {
            ConceptSource::query()->updateOrCreate(['code' => $code], ['name' => $name, 'version' => $version, 'url' => $url]);
        }

        $terminology = app(Terminology::class);

        foreach (ObservationCode::cases() as $code) {
            $terminology->register(
                ConceptClass::Observation,
                'OBS-'.strtoupper($code->value),
                $code->label(),
                isset($this->observationLoinc[$code->value]) ? ['LOINC' => $this->observationLoinc[$code->value]] : [],
                [],
                ['datatype' => $code->isText() ? 'text' : 'numeric', 'unit' => $code->unit()],
            );
        }

        foreach (LabTest::query()->get() as $test) {
            $concept = $terminology->register(
                ConceptClass::LabTest,
                'LAB-'.$test->code,
                $test->name,
                isset($this->labLoinc[$test->code]) ? ['LOINC' => $this->labLoinc[$test->code]] : [],
                [],
                ['datatype' => $test->reference_text !== null ? 'text' : 'numeric', 'unit' => $test->unit],
            );

            if ($test->concept_id !== $concept->id) {
                $test->forceFill(['concept_id' => $concept->id])->save();
            }
        }

        foreach ($this->drugs as [$name, $synonyms]) {
            $terminology->register(ConceptClass::Drug, 'DRUG-'.$this->slug($name), $name, ['NEML' => $this->slug($name)], $synonyms);
        }

        foreach ($this->allergens as [$name, $synonyms]) {
            $terminology->register(ConceptClass::Allergen, 'ALG-'.$this->slug($name), $name, [], $synonyms);
        }

        foreach ($this->vaccines as [$name, $synonyms]) {
            $terminology->register(ConceptClass::Vaccine, 'VAX-'.$this->slug($name), $name, [], $synonyms);
        }

        // Stock items take the drug concept whose name they carry.
        foreach (InventoryItem::query()->whereNull('concept_id')->get() as $item) {
            $concept = $terminology->match(ConceptClass::Drug, $item->name);

            if ($concept !== null) {
                $item->forceFill(['concept_id' => $concept->id])->save();
            }
        }
    }

    private function slug(string $name): string
    {
        return strtoupper(Str::slug($name, '-'));
    }
}

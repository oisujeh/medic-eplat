<?php

namespace Database\Seeders;

use App\Models\IcdCode;
use Illuminate\Database\Seeder;

class IcdCodesSeeder extends Seeder
{
    /**
     * A starter ICD-10 catalogue: the diagnoses Nigerian facilities record
     * most, chosen so the NHMIS morbidity groups all have codes behind them.
     * `php artisan icd:import` loads the full WHO list on top.
     *
     * [code, description, chapter]
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    protected array $codes = [
        // Infectious and parasitic diseases
        ['A00', 'Cholera', 'Infectious and parasitic diseases'],
        ['A01.0', 'Typhoid fever', 'Infectious and parasitic diseases'],
        ['A02', 'Other salmonella infections', 'Infectious and parasitic diseases'],
        ['A03', 'Shigellosis', 'Infectious and parasitic diseases'],
        ['A06', 'Amoebiasis', 'Infectious and parasitic diseases'],
        ['A08', 'Viral and other specified intestinal infections', 'Infectious and parasitic diseases'],
        ['A09', 'Diarrhoea and gastroenteritis of presumed infectious origin', 'Infectious and parasitic diseases'],
        ['A15', 'Respiratory tuberculosis, bacteriologically confirmed', 'Infectious and parasitic diseases'],
        ['A16', 'Respiratory tuberculosis, not confirmed', 'Infectious and parasitic diseases'],
        ['A18', 'Tuberculosis of other organs', 'Infectious and parasitic diseases'],
        ['A20', 'Plague', 'Infectious and parasitic diseases'],
        ['A30', 'Leprosy', 'Infectious and parasitic diseases'],
        ['A33', 'Tetanus neonatorum', 'Infectious and parasitic diseases'],
        ['A35', 'Other tetanus', 'Infectious and parasitic diseases'],
        ['A36', 'Diphtheria', 'Infectious and parasitic diseases'],
        ['A37', 'Whooping cough', 'Infectious and parasitic diseases'],
        ['A39', 'Meningococcal infection', 'Infectious and parasitic diseases'],
        ['A41.9', 'Sepsis, unspecified', 'Infectious and parasitic diseases'],
        ['A50', 'Congenital syphilis', 'Infectious and parasitic diseases'],
        ['A53.9', 'Syphilis, unspecified', 'Infectious and parasitic diseases'],
        ['A54', 'Gonococcal infection', 'Infectious and parasitic diseases'],
        ['A64', 'Unspecified sexually transmitted disease', 'Infectious and parasitic diseases'],
        ['A80', 'Acute poliomyelitis', 'Infectious and parasitic diseases'],
        ['A82', 'Rabies', 'Infectious and parasitic diseases'],
        ['A90', 'Dengue fever', 'Infectious and parasitic diseases'],
        ['A95', 'Yellow fever', 'Infectious and parasitic diseases'],
        ['A96.2', 'Lassa fever', 'Infectious and parasitic diseases'],
        ['B01', 'Varicella (chickenpox)', 'Infectious and parasitic diseases'],
        ['B05', 'Measles', 'Infectious and parasitic diseases'],
        ['B15', 'Acute hepatitis A', 'Infectious and parasitic diseases'],
        ['B16', 'Acute hepatitis B', 'Infectious and parasitic diseases'],
        ['B18.1', 'Chronic viral hepatitis B without delta-agent', 'Infectious and parasitic diseases'],
        ['B18.2', 'Chronic viral hepatitis C', 'Infectious and parasitic diseases'],
        ['B20', 'HIV disease resulting in infectious and parasitic diseases', 'Infectious and parasitic diseases'],
        ['B24', 'Unspecified HIV disease', 'Infectious and parasitic diseases'],
        ['B26', 'Mumps', 'Infectious and parasitic diseases'],
        ['B35', 'Dermatophytosis', 'Infectious and parasitic diseases'],
        ['B37', 'Candidiasis', 'Infectious and parasitic diseases'],
        ['B50', 'Plasmodium falciparum malaria', 'Infectious and parasitic diseases'],
        ['B50.0', 'Plasmodium falciparum malaria with cerebral complications', 'Infectious and parasitic diseases'],
        ['B50.8', 'Plasmodium falciparum malaria with other severe complications', 'Infectious and parasitic diseases'],
        ['B54', 'Unspecified malaria', 'Infectious and parasitic diseases'],
        ['B65', 'Schistosomiasis (bilharziasis)', 'Infectious and parasitic diseases'],
        ['B73', 'Onchocerciasis', 'Infectious and parasitic diseases'],
        ['B74', 'Filariasis', 'Infectious and parasitic diseases'],
        ['B76', 'Hookworm diseases', 'Infectious and parasitic diseases'],
        ['B77', 'Ascariasis', 'Infectious and parasitic diseases'],
        ['B82.9', 'Intestinal parasitism, unspecified', 'Infectious and parasitic diseases'],
        ['B86', 'Scabies', 'Infectious and parasitic diseases'],

        // Neoplasms
        ['C50', 'Malignant neoplasm of breast', 'Neoplasms'],
        ['C53', 'Malignant neoplasm of cervix uteri', 'Neoplasms'],
        ['C61', 'Malignant neoplasm of prostate', 'Neoplasms'],
        ['C22', 'Malignant neoplasm of liver', 'Neoplasms'],
        ['D25', 'Leiomyoma of uterus (fibroids)', 'Neoplasms'],

        // Blood
        ['D50', 'Iron deficiency anaemia', 'Diseases of the blood'],
        ['D57.0', 'Sickle-cell anaemia with crisis', 'Diseases of the blood'],
        ['D57.1', 'Sickle-cell anaemia without crisis', 'Diseases of the blood'],
        ['D64.9', 'Anaemia, unspecified', 'Diseases of the blood'],

        // Endocrine, nutritional and metabolic
        ['E10', 'Type 1 diabetes mellitus', 'Endocrine, nutritional and metabolic diseases'],
        ['E11', 'Type 2 diabetes mellitus', 'Endocrine, nutritional and metabolic diseases'],
        ['E11.9', 'Type 2 diabetes mellitus without complications', 'Endocrine, nutritional and metabolic diseases'],
        ['E14', 'Unspecified diabetes mellitus', 'Endocrine, nutritional and metabolic diseases'],
        ['E04', 'Other non-toxic goitre', 'Endocrine, nutritional and metabolic diseases'],
        ['E05', 'Thyrotoxicosis', 'Endocrine, nutritional and metabolic diseases'],
        ['E40', 'Kwashiorkor', 'Endocrine, nutritional and metabolic diseases'],
        ['E41', 'Nutritional marasmus', 'Endocrine, nutritional and metabolic diseases'],
        ['E43', 'Unspecified severe protein-energy malnutrition', 'Endocrine, nutritional and metabolic diseases'],
        ['E44', 'Protein-energy malnutrition of moderate and mild degree', 'Endocrine, nutritional and metabolic diseases'],
        ['E46', 'Unspecified protein-energy malnutrition', 'Endocrine, nutritional and metabolic diseases'],
        ['E66', 'Obesity', 'Endocrine, nutritional and metabolic diseases'],
        ['E86', 'Volume depletion (dehydration)', 'Endocrine, nutritional and metabolic diseases'],

        // Mental and behavioural
        ['F10', 'Mental and behavioural disorders due to use of alcohol', 'Mental and behavioural disorders'],
        ['F20', 'Schizophrenia', 'Mental and behavioural disorders'],
        ['F32', 'Depressive episode', 'Mental and behavioural disorders'],
        ['F41', 'Other anxiety disorders', 'Mental and behavioural disorders'],

        // Nervous system
        ['G00', 'Bacterial meningitis, not elsewhere classified', 'Diseases of the nervous system'],
        ['G03.9', 'Meningitis, unspecified', 'Diseases of the nervous system'],
        ['G40', 'Epilepsy', 'Diseases of the nervous system'],
        ['G43', 'Migraine', 'Diseases of the nervous system'],

        // Eye and ear
        ['H10', 'Conjunctivitis', 'Diseases of the eye'],
        ['H25', 'Senile cataract', 'Diseases of the eye'],
        ['H40', 'Glaucoma', 'Diseases of the eye'],
        ['H66', 'Suppurative and unspecified otitis media', 'Diseases of the ear'],

        // Circulatory
        ['I10', 'Essential (primary) hypertension', 'Diseases of the circulatory system'],
        ['I11', 'Hypertensive heart disease', 'Diseases of the circulatory system'],
        ['I20', 'Angina pectoris', 'Diseases of the circulatory system'],
        ['I21', 'Acute myocardial infarction', 'Diseases of the circulatory system'],
        ['I50', 'Heart failure', 'Diseases of the circulatory system'],
        ['I64', 'Stroke, not specified as haemorrhage or infarction', 'Diseases of the circulatory system'],
        ['I84', 'Haemorrhoids', 'Diseases of the circulatory system'],

        // Respiratory
        ['J00', 'Acute nasopharyngitis (common cold)', 'Diseases of the respiratory system'],
        ['J02', 'Acute pharyngitis', 'Diseases of the respiratory system'],
        ['J03', 'Acute tonsillitis', 'Diseases of the respiratory system'],
        ['J06.9', 'Acute upper respiratory infection, unspecified', 'Diseases of the respiratory system'],
        ['J11', 'Influenza, virus not identified', 'Diseases of the respiratory system'],
        ['J15', 'Bacterial pneumonia, not elsewhere classified', 'Diseases of the respiratory system'],
        ['J18.9', 'Pneumonia, unspecified', 'Diseases of the respiratory system'],
        ['J20', 'Acute bronchitis', 'Diseases of the respiratory system'],
        ['J21', 'Acute bronchiolitis', 'Diseases of the respiratory system'],
        ['J22', 'Unspecified acute lower respiratory infection', 'Diseases of the respiratory system'],
        ['J44', 'Chronic obstructive pulmonary disease', 'Diseases of the respiratory system'],
        ['J45', 'Asthma', 'Diseases of the respiratory system'],

        // Digestive
        ['K02', 'Dental caries', 'Diseases of the digestive system'],
        ['K04', 'Diseases of pulp and periapical tissues', 'Diseases of the digestive system'],
        ['K05', 'Gingivitis and periodontal diseases', 'Diseases of the digestive system'],
        ['K25', 'Gastric ulcer', 'Diseases of the digestive system'],
        ['K26', 'Duodenal ulcer', 'Diseases of the digestive system'],
        ['K27', 'Peptic ulcer, site unspecified', 'Diseases of the digestive system'],
        ['K29', 'Gastritis and duodenitis', 'Diseases of the digestive system'],
        ['K30', 'Dyspepsia', 'Diseases of the digestive system'],
        ['K35', 'Acute appendicitis', 'Diseases of the digestive system'],
        ['K40', 'Inguinal hernia', 'Diseases of the digestive system'],
        ['K70', 'Alcoholic liver disease', 'Diseases of the digestive system'],
        ['K74', 'Fibrosis and cirrhosis of liver', 'Diseases of the digestive system'],

        // Skin
        ['L01', 'Impetigo', 'Diseases of the skin'],
        ['L02', 'Cutaneous abscess, furuncle and carbuncle', 'Diseases of the skin'],
        ['L03', 'Cellulitis', 'Diseases of the skin'],
        ['L20', 'Atopic dermatitis', 'Diseases of the skin'],
        ['L30.9', 'Dermatitis, unspecified', 'Diseases of the skin'],

        // Musculoskeletal
        ['M06', 'Rheumatoid arthritis', 'Diseases of the musculoskeletal system'],
        ['M10', 'Gout', 'Diseases of the musculoskeletal system'],
        ['M17', 'Gonarthrosis (arthrosis of knee)', 'Diseases of the musculoskeletal system'],
        ['M54.5', 'Low back pain', 'Diseases of the musculoskeletal system'],
        ['M79.1', 'Myalgia', 'Diseases of the musculoskeletal system'],

        // Genitourinary
        ['N10', 'Acute tubulo-interstitial nephritis (pyelonephritis)', 'Diseases of the genitourinary system'],
        ['N18', 'Chronic kidney disease', 'Diseases of the genitourinary system'],
        ['N20', 'Calculus of kidney and ureter', 'Diseases of the genitourinary system'],
        ['N39.0', 'Urinary tract infection, site not specified', 'Diseases of the genitourinary system'],
        ['N40', 'Hyperplasia of prostate', 'Diseases of the genitourinary system'],
        ['N70', 'Salpingitis and oophoritis', 'Diseases of the genitourinary system'],
        ['N73.9', 'Female pelvic inflammatory disease, unspecified', 'Diseases of the genitourinary system'],
        ['N76', 'Other inflammation of vagina and vulva', 'Diseases of the genitourinary system'],
        ['N92', 'Excessive, frequent and irregular menstruation', 'Diseases of the genitourinary system'],
        ['N97', 'Female infertility', 'Diseases of the genitourinary system'],

        // Pregnancy, childbirth and the puerperium
        ['O00', 'Ectopic pregnancy', 'Pregnancy, childbirth and the puerperium'],
        ['O03', 'Spontaneous abortion', 'Pregnancy, childbirth and the puerperium'],
        ['O14', 'Pre-eclampsia', 'Pregnancy, childbirth and the puerperium'],
        ['O15', 'Eclampsia', 'Pregnancy, childbirth and the puerperium'],
        ['O21', 'Excessive vomiting in pregnancy', 'Pregnancy, childbirth and the puerperium'],
        ['O44', 'Placenta praevia', 'Pregnancy, childbirth and the puerperium'],
        ['O45', 'Premature separation of placenta (abruptio placentae)', 'Pregnancy, childbirth and the puerperium'],
        ['O64', 'Obstructed labour due to malposition and malpresentation of fetus', 'Pregnancy, childbirth and the puerperium'],
        ['O72', 'Postpartum haemorrhage', 'Pregnancy, childbirth and the puerperium'],
        ['O80', 'Single spontaneous delivery', 'Pregnancy, childbirth and the puerperium'],
        ['O82', 'Single delivery by caesarean section', 'Pregnancy, childbirth and the puerperium'],
        ['O85', 'Puerperal sepsis', 'Pregnancy, childbirth and the puerperium'],
        ['O98.6', 'Protozoal diseases complicating pregnancy (malaria in pregnancy)', 'Pregnancy, childbirth and the puerperium'],
        ['O99.0', 'Anaemia complicating pregnancy, childbirth and the puerperium', 'Pregnancy, childbirth and the puerperium'],

        // Perinatal
        ['P07', 'Disorders related to short gestation and low birth weight', 'Certain conditions originating in the perinatal period'],
        ['P21', 'Birth asphyxia', 'Certain conditions originating in the perinatal period'],
        ['P36', 'Bacterial sepsis of newborn', 'Certain conditions originating in the perinatal period'],
        ['P59', 'Neonatal jaundice from other and unspecified causes', 'Certain conditions originating in the perinatal period'],

        // Symptoms and signs
        ['R10.4', 'Other and unspecified abdominal pain', 'Symptoms, signs and abnormal findings'],
        ['R50.9', 'Fever, unspecified', 'Symptoms, signs and abnormal findings'],
        ['R51', 'Headache', 'Symptoms, signs and abnormal findings'],
        ['R56', 'Convulsions, not elsewhere classified', 'Symptoms, signs and abnormal findings'],

        // Injury, poisoning and external causes
        ['S06', 'Intracranial injury', 'Injury, poisoning and certain other consequences of external causes'],
        ['S52', 'Fracture of forearm', 'Injury, poisoning and certain other consequences of external causes'],
        ['S72', 'Fracture of femur', 'Injury, poisoning and certain other consequences of external causes'],
        ['S82', 'Fracture of lower leg, including ankle', 'Injury, poisoning and certain other consequences of external causes'],
        ['T14.1', 'Open wound of unspecified body region', 'Injury, poisoning and certain other consequences of external causes'],
        ['T30', 'Burn and corrosion, body region unspecified', 'Injury, poisoning and certain other consequences of external causes'],
        ['T63.0', 'Toxic effect of snake venom', 'Injury, poisoning and certain other consequences of external causes'],
        ['T65.9', 'Toxic effect of unspecified substance (poisoning)', 'Injury, poisoning and certain other consequences of external causes'],
        ['V89.2', 'Person injured in unspecified motor-vehicle accident, traffic', 'External causes of morbidity'],

        // Factors influencing health status
        ['Z00.0', 'General medical examination', 'Factors influencing health status'],
        ['Z34', 'Supervision of normal pregnancy', 'Factors influencing health status'],
        ['Z30', 'Contraceptive management', 'Factors influencing health status'],
        ['Z23', 'Need for immunization against single bacterial diseases', 'Factors influencing health status'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->codes as [$code, $description, $chapter]) {
            IcdCode::query()->updateOrCreate(
                ['code' => $code],
                ['description' => $description, 'chapter' => $chapter, 'is_active' => true],
            );
        }
    }
}

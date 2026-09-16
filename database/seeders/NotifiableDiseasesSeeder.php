<?php

namespace Database\Seeders;

use App\Enums\NotifiableDiseaseCategory;
use App\Models\NotifiableDisease;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Nigeria's IDSR priority diseases, mapped to the ICD-10 codes that identify
 * them. Editable seed data: the catalogue can be trimmed or extended per
 * facility, and the ICD prefixes can be widened when the diagnosis catalogue
 * grows. Non-communicable conditions on the IDSR monthly form (hypertension,
 * diabetes, epilepsy, injuries) are left out so routine chronic diagnoses do
 * not flood the surveillance register.
 */
class NotifiableDiseasesSeeder extends Seeder
{
    /**
     * [name, category, icd prefixes, case definition]
     *
     * @var array<int, array{0: string, 1: NotifiableDiseaseCategory, 2: array<int, string>, 3: string}>
     */
    protected array $diseases = [
        // ------------------------------------------------ Immediately notifiable
        ['Cholera', NotifiableDiseaseCategory::Immediate, ['A00'], 'Acute watery diarrhoea, with or without vomiting, in a patient aged 2 years or older; or any patient with acute watery diarrhoea where cholera is present in the area.'],
        ['Lassa fever', NotifiableDiseaseCategory::Immediate, ['A96.2'], 'Fever for more than 48 hours not responding to antimalarials or antibiotics, with any of: sore throat, bleeding, oedema, chest or abdominal pain, or contact with a confirmed case.'],
        ['Yellow fever', NotifiableDiseaseCategory::Immediate, ['A95'], 'Acute onset of fever with jaundice within 14 days of onset of the first symptoms.'],
        ['Measles', NotifiableDiseaseCategory::Immediate, ['B05'], 'Fever with generalised maculopapular rash and any of cough, coryza or conjunctivitis.'],
        ['Cerebrospinal meningitis', NotifiableDiseaseCategory::Immediate, ['A39', 'G00'], 'Sudden onset of fever with stiff neck, altered consciousness or other meningeal signs; in infants, a bulging fontanelle.'],
        ['Viral haemorrhagic fever (Ebola, Marburg)', NotifiableDiseaseCategory::Immediate, ['A98.3', 'A98.4'], 'Acute fever with unexplained bleeding, or fever in a person who had contact with a suspected or confirmed VHF case.'],
        ['Dengue fever', NotifiableDiseaseCategory::Immediate, ['A90', 'A91'], 'Acute fever with two or more of headache, retro-orbital pain, myalgia, arthralgia, rash or haemorrhagic manifestations.'],
        ['Rift Valley fever', NotifiableDiseaseCategory::Immediate, ['A92.4'], 'Acute febrile illness with contact with sick or dead livestock, or unexplained bleeding, encephalitis or retinitis.'],
        ['Acute flaccid paralysis (poliomyelitis)', NotifiableDiseaseCategory::Immediate, ['A80'], 'Sudden onset of flaccid weakness or paralysis in any limb of a child under 15 years, from any cause.'],
        ['Guinea worm (dracunculiasis)', NotifiableDiseaseCategory::Immediate, ['B72'], 'A skin lesion with emergence of a worm.'],
        ['Neonatal tetanus', NotifiableDiseaseCategory::Immediate, ['A33'], 'A newborn who sucked and cried normally for the first two days, then between day 3 and 28 stopped sucking and became stiff or had spasms.'],
        ['Anthrax', NotifiableDiseaseCategory::Immediate, ['A22'], 'Acute illness with a skin lesion progressing to a black eschar, or respiratory or gastrointestinal illness after contact with animals or animal products.'],
        ['Plague', NotifiableDiseaseCategory::Immediate, ['A20'], 'Sudden fever, chills and painful lymphadenopathy, or pneumonia, in a person with exposure to rodents or fleas.'],
        ['Human rabies', NotifiableDiseaseCategory::Immediate, ['A82'], 'Acute neurological syndrome with hydrophobia or aerophobia after a bite or scratch from a suspect animal.'],
        ['Diphtheria', NotifiableDiseaseCategory::Immediate, ['A36'], 'Laryngitis, pharyngitis or tonsillitis with an adherent membrane of the tonsils, pharynx or nose.'],
        ['Pertussis (whooping cough)', NotifiableDiseaseCategory::Immediate, ['A37'], 'Cough lasting at least two weeks with paroxysms, inspiratory whoop or post-tussive vomiting.'],
        ['Mpox', NotifiableDiseaseCategory::Immediate, ['B04'], 'Unexplained rash with fever and one or more of headache, lymphadenopathy, myalgia or asthenia.'],
        ['COVID-19', NotifiableDiseaseCategory::Immediate, ['U07.1', 'U07.2'], 'Acute respiratory illness with fever and cough or breathlessness, or contact with a confirmed case, with a positive test where available.'],
        ['Avian influenza in humans', NotifiableDiseaseCategory::Immediate, ['J09'], 'Severe acute respiratory illness after contact with sick or dead poultry or birds.'],

        // --------------------------------------------------- Weekly reportable
        ['Malaria', NotifiableDiseaseCategory::Weekly, ['B50', 'B51', 'B52', 'B53', 'B54'], 'Fever with a positive malaria test (RDT or microscopy).'],
        ['Typhoid fever', NotifiableDiseaseCategory::Weekly, ['A01'], 'Sustained fever with headache, malaise and constipation or diarrhoea, confirmed by culture.'],
        ['Tuberculosis', NotifiableDiseaseCategory::Weekly, ['A15', 'A16', 'A17', 'A18', 'A19'], 'Cough of two weeks or more with a positive smear, GeneXpert or clinical diagnosis.'],
        ['Viral hepatitis B', NotifiableDiseaseCategory::Weekly, ['B16', 'B18.0', 'B18.1'], 'Acute illness with jaundice and a positive hepatitis B surface antigen.'],
        ['Viral hepatitis C', NotifiableDiseaseCategory::Weekly, ['B17.1', 'B18.2'], 'Hepatitis C antibody or RNA positive.'],
        ['Leprosy', NotifiableDiseaseCategory::Weekly, ['A30'], 'Hypopigmented or reddish skin lesion with loss of sensation, thickened peripheral nerves, or positive skin smear.'],
        ['Buruli ulcer', NotifiableDiseaseCategory::Weekly, ['A31.1'], 'Painless nodule, plaque, oedema or ulcer with undermined edges in an endemic area.'],
        ['Noma', NotifiableDiseaseCategory::Weekly, ['A69.0'], 'Gangrenous stomatitis destroying the soft and hard tissues of the face.'],
        ['Onchocerciasis', NotifiableDiseaseCategory::Weekly, ['B73'], 'Nodules, dermatitis or visual impairment in an endemic area with microfilariae on skin snip.'],
        ['Lymphatic filariasis', NotifiableDiseaseCategory::Weekly, ['B74'], 'Hydrocele or lymphoedema of the limb in an endemic area.'],
        ['Schistosomiasis', NotifiableDiseaseCategory::Weekly, ['B65'], 'Haematuria or bloody stool with eggs on microscopy in an endemic area.'],
        ['Trachoma', NotifiableDiseaseCategory::Weekly, ['A71'], 'Follicular or intense inflammation of the upper tarsal conjunctiva, or trichiasis.'],
        ['Snake bite', NotifiableDiseaseCategory::Weekly, ['T63.0'], 'History of snake bite with local or systemic envenoming.'],
        ['Acute diarrhoea', NotifiableDiseaseCategory::Weekly, ['A09'], 'Three or more loose stools in 24 hours, reported for children under five with dehydration.'],
        ['Pneumonia', NotifiableDiseaseCategory::Weekly, ['J12', 'J13', 'J14', 'J15', 'J16', 'J17', 'J18'], 'Cough or difficulty breathing with fast breathing or chest indrawing, reported for children under five.'],
        ['Severe acute malnutrition', NotifiableDiseaseCategory::Weekly, ['E40', 'E41', 'E42', 'E43', 'E44', 'E45', 'E46'], 'Child aged 6 to 59 months with MUAC below 11.5 cm, weight-for-height below -3 SD, or bilateral pitting oedema.'],
        ['Chikungunya', NotifiableDiseaseCategory::Weekly, ['A92.0'], 'Acute fever with severe arthralgia not explained by another condition.'],
    ];

    /**
     * Hours allowed to reach the DSNO for an immediately notifiable case.
     */
    private const IMMEDIATE_NOTIFICATION_HOURS = 24;

    /**
     * Diseases whose response includes tracing and following up contacts.
     *
     * @var array<int, string>
     */
    private const CONTACT_TRACING = [
        'cholera', 'lassa-fever', 'measles', 'cerebrospinal-meningitis',
        'viral-haemorrhagic-fever-ebola-marburg', 'mpox',
    ];

    /**
     * Seed the IDSR catalogue.
     */
    public function run(): void
    {
        foreach ($this->diseases as $index => [$name, $category, $prefixes, $definition]) {
            $slug = Str::slug($name);

            NotifiableDisease::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'category' => $category,
                    'detection' => NotifiableDisease::DETECTION_DIAGNOSIS,
                    'icd_prefixes' => $prefixes,
                    'case_definition' => $definition,
                    'notification_hours' => $category === NotifiableDiseaseCategory::Immediate ? self::IMMEDIATE_NOTIFICATION_HOURS : null,
                    'requires_contact_tracing' => in_array($slug, self::CONTACT_TRACING, true),
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}

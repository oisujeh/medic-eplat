<?php

namespace Database\Seeders;

use App\Enums\ConceptClass;
use App\Enums\EncounterType;
use App\Enums\FormEntryMode;
use App\Models\ConceptSource;
use App\Models\Form;
use App\Services\FormCatalogue;
use App\Services\Terminology;
use Illuminate\Database\Seeder;

/**
 * The starter forms. Theatre is handled as forms in v1 (decision log), so
 * the operation note ships here as a published form rather than as code.
 * Every field binds to a local concept registered alongside it. A form the
 * facility already has is left as it is.
 */
class FormsSeeder extends Seeder
{
    public function run(): void
    {
        ConceptSource::firstOrCreate(['code' => ConceptSource::LOCAL], ['name' => 'Facility local codes']);

        $terminology = app(Terminology::class);

        foreach ($this->concepts() as [$code, $name, $datatype, $unit]) {
            $terminology->register(ConceptClass::Observation, $code, $name, [], [], ['datatype' => $datatype, 'unit' => $unit]);
        }

        if (Form::where('slug', 'operation-note')->exists()) {
            return;
        }

        $catalogue = app(FormCatalogue::class);

        $form = $catalogue->create([
            'slug' => 'operation-note',
            'name' => 'Operation note',
            'description' => 'The surgeon\'s record of a procedure: anaesthesia, timings, findings, blood loss, complications and specimens.',
            'entry_mode' => FormEntryMode::Guided->value,
            'encounter_types' => [EncounterType::Consultation->value, EncounterType::WardRound->value],
            'roles' => ['physician'],
            'definition' => $this->operationNote(),
        ]);

        $catalogue->publish($form, null, 'Seeded starter form');
    }

    /**
     * code, name, datatype, unit
     *
     * @return list<array{0: string, 1: string, 2: string|null, 3: string|null}>
     */
    private function concepts(): array
    {
        return [
            ['OPN-PROCEDURE', 'Procedure performed', 'text', null],
            ['OPN-URGENCY', 'Operation urgency', 'coded', null],
            ['OPN-URGENCY-ELECTIVE', 'Elective operation', null, null],
            ['OPN-URGENCY-EMERGENCY', 'Emergency operation', null, null],
            ['OPN-ANAESTHESIA', 'Anaesthesia type', 'coded', null],
            ['OPN-ANAESTHESIA-GENERAL', 'General anaesthesia', null, null],
            ['OPN-ANAESTHESIA-SPINAL', 'Spinal anaesthesia', null, null],
            ['OPN-ANAESTHESIA-REGIONAL', 'Regional block', null, null],
            ['OPN-ANAESTHESIA-LOCAL', 'Local anaesthesia', null, null],
            ['OPN-ANAESTHESIA-SEDATION', 'Sedation', null, null],
            ['OPN-SURGEON', 'Operating surgeon', 'text', null],
            ['OPN-ASSISTANTS', 'Surgical assistants', 'text', null],
            ['OPN-START', 'Operation start time', 'datetime', null],
            ['OPN-END', 'Operation end time', 'datetime', null],
            ['OPN-DURATION', 'Operation duration', 'numeric', 'min'],
            ['OPN-INCISION', 'Incision', 'text', null],
            ['OPN-FINDINGS', 'Operative findings', 'text', null],
            ['OPN-EBL', 'Estimated blood loss', 'numeric', 'ml'],
            ['OPN-TRANSFUSED', 'Blood transfused during operation', 'boolean', null],
            ['OPN-TRANSFUSED-UNITS', 'Units of blood transfused', 'numeric', 'units'],
            ['OPN-COMPLICATION', 'Intra-operative complication', 'boolean', null],
            ['OPN-COMPLICATION-DETAIL', 'Intra-operative complication detail', 'text', null],
            ['OPN-SPECIMEN', 'Specimen sent', null, null],
            ['OPN-SPECIMEN-TYPE', 'Specimen type', 'text', null],
            ['OPN-SPECIMEN-SITE', 'Specimen site', 'text', null],
            ['OPN-SPECIMEN-HISTOLOGY', 'Specimen sent for histology', 'boolean', null],
            ['OPN-DRAIN', 'Drain left in situ', 'boolean', null],
            ['OPN-POSTOP', 'Post-operative instructions', 'text', null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationNote(): array
    {
        return [
            'pages' => [
                [
                    'key' => 'page_procedure',
                    'title' => 'Procedure',
                    'sections' => [
                        [
                            'key' => 'operation',
                            'title' => 'Operation',
                            'fields' => [
                                ['key' => 'procedure', 'type' => 'text', 'label' => 'Procedure performed', 'concept' => 'OPN-PROCEDURE', 'required' => true, 'max_length' => 200],
                                ['key' => 'urgency', 'type' => 'coded', 'label' => 'Urgency', 'concept' => 'OPN-URGENCY', 'required' => true, 'options' => [
                                    ['value' => 'elective', 'label' => 'Elective', 'concept' => 'OPN-URGENCY-ELECTIVE'],
                                    ['value' => 'emergency', 'label' => 'Emergency', 'concept' => 'OPN-URGENCY-EMERGENCY'],
                                ]],
                                ['key' => 'anaesthesia', 'type' => 'coded', 'label' => 'Anaesthesia', 'concept' => 'OPN-ANAESTHESIA', 'required' => true, 'options' => [
                                    ['value' => 'general', 'label' => 'General', 'concept' => 'OPN-ANAESTHESIA-GENERAL'],
                                    ['value' => 'spinal', 'label' => 'Spinal', 'concept' => 'OPN-ANAESTHESIA-SPINAL'],
                                    ['value' => 'regional', 'label' => 'Regional block', 'concept' => 'OPN-ANAESTHESIA-REGIONAL'],
                                    ['value' => 'local', 'label' => 'Local', 'concept' => 'OPN-ANAESTHESIA-LOCAL'],
                                    ['value' => 'sedation', 'label' => 'Sedation', 'concept' => 'OPN-ANAESTHESIA-SEDATION'],
                                ]],
                                ['key' => 'surgeon', 'type' => 'text', 'label' => 'Surgeon', 'concept' => 'OPN-SURGEON', 'required' => true, 'max_length' => 120],
                                ['key' => 'assistants', 'type' => 'text', 'label' => 'Assistants', 'concept' => 'OPN-ASSISTANTS', 'max_length' => 200],
                            ],
                        ],
                        [
                            'key' => 'timing',
                            'title' => 'Timing',
                            'fields' => [
                                ['key' => 'start', 'type' => 'datetime', 'label' => 'Started', 'concept' => 'OPN-START', 'required' => true, 'not_future' => true],
                                ['key' => 'end', 'type' => 'datetime', 'label' => 'Finished', 'concept' => 'OPN-END', 'required' => true, 'not_future' => true, 'validate' => [
                                    ['when' => ['field' => 'end', 'op' => 'lte', 'value' => ['field' => 'start']], 'message' => 'The operation must finish after it starts.'],
                                ]],
                                ['key' => 'duration', 'type' => 'calculated', 'label' => 'Duration', 'concept' => 'OPN-DURATION', 'unit' => 'min', 'calc' => [
                                    'op' => 'round', 'args' => [['op' => 'minutes_between', 'args' => [['field' => 'start'], ['field' => 'end']]], 0],
                                ]],
                            ],
                        ],
                        [
                            'key' => 'operative',
                            'title' => 'Operative record',
                            'fields' => [
                                ['key' => 'incision', 'type' => 'text', 'label' => 'Incision', 'concept' => 'OPN-INCISION', 'max_length' => 200],
                                ['key' => 'findings', 'type' => 'text', 'label' => 'Findings', 'concept' => 'OPN-FINDINGS', 'required' => true, 'rows' => 4],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'page_outcome',
                    'title' => 'Outcome',
                    'sections' => [
                        [
                            'key' => 'blood',
                            'title' => 'Blood loss',
                            'fields' => [
                                ['key' => 'ebl', 'type' => 'numeric', 'label' => 'Estimated blood loss', 'concept' => 'OPN-EBL', 'unit' => 'ml', 'min' => 0, 'max' => 5000, 'integer' => true],
                                ['key' => 'transfused', 'type' => 'boolean', 'label' => 'Blood transfused', 'concept' => 'OPN-TRANSFUSED', 'default' => false],
                                ['key' => 'transfused_units', 'type' => 'numeric', 'label' => 'Units transfused', 'concept' => 'OPN-TRANSFUSED-UNITS', 'unit' => 'units', 'min' => 1, 'max' => 20, 'integer' => true, 'required' => true,
                                    'show_when' => ['field' => 'transfused', 'op' => 'eq', 'value' => true]],
                            ],
                        ],
                        [
                            'key' => 'complications',
                            'title' => 'Complications',
                            'fields' => [
                                ['key' => 'complication', 'type' => 'boolean', 'label' => 'Intra-operative complication', 'concept' => 'OPN-COMPLICATION', 'required' => true, 'default' => false],
                                ['key' => 'complication_detail', 'type' => 'text', 'label' => 'Describe the complication', 'concept' => 'OPN-COMPLICATION-DETAIL', 'required' => true, 'rows' => 3,
                                    'show_when' => ['field' => 'complication', 'op' => 'eq', 'value' => true]],
                            ],
                        ],
                        [
                            'key' => 'specimens',
                            'title' => 'Specimens sent',
                            'concept' => 'OPN-SPECIMEN',
                            'repeat' => ['min' => 0, 'max' => 10, 'add_label' => 'Add specimen'],
                            'fields' => [
                                ['key' => 'specimen_type', 'type' => 'text', 'label' => 'Specimen', 'concept' => 'OPN-SPECIMEN-TYPE', 'required' => true, 'max_length' => 120],
                                ['key' => 'specimen_site', 'type' => 'text', 'label' => 'Site', 'concept' => 'OPN-SPECIMEN-SITE', 'max_length' => 120],
                                ['key' => 'histology', 'type' => 'boolean', 'label' => 'Sent for histology', 'concept' => 'OPN-SPECIMEN-HISTOLOGY', 'default' => true],
                            ],
                        ],
                        [
                            'key' => 'closure',
                            'title' => 'Closure and instructions',
                            'fields' => [
                                ['key' => 'drain', 'type' => 'boolean', 'label' => 'Drain left in situ', 'concept' => 'OPN-DRAIN', 'default' => false],
                                ['key' => 'postop', 'type' => 'text', 'label' => 'Post-operative instructions', 'concept' => 'OPN-POSTOP', 'required' => true, 'rows' => 4],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

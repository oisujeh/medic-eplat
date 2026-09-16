<?php

namespace Database\Seeders;

use App\Models\ServiceCharge;
use App\Models\ServicePoint;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;

/**
 * The pathways a general hospital runs. Each is a list of service points
 * (by slug) with the encounter opened there and the fee posted on signing,
 * and the moves allowed between them with the suggested one marked.
 */
class WorkflowsSeeder extends Seeder
{
    /**
     * slug => [name, description, is_default, stages, transitions]
     *   stages: service point slug => [encounter type|null, charge code|null, is entry]
     *   transitions: from slug => [to slug => is default]
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: array<string, array{0: string|null, 1: string|null, 2: bool}>, 4: array<string, array<string, bool>>}>
     */
    protected array $workflows = [
        'opd' => [
            'Outpatient (OPD)',
            'Triage, consultation, investigations, medicines and payment.',
            true,
            [
                'triage' => ['triage', null, true],
                'consultation' => ['consultation', ServiceCharge::CONSULTATION, true],
                'laboratory' => [null, null, false],
                'pharmacy' => [null, null, false],
                'billing' => [null, null, false],
            ],
            [
                'triage' => ['consultation' => true],
                'consultation' => ['pharmacy' => true, 'laboratory' => false, 'billing' => false],
                'laboratory' => ['consultation' => true, 'pharmacy' => false],
                'pharmacy' => ['billing' => true],
            ],
        ],
        'emergency' => [
            'Emergency',
            'Straight to the clinician; investigations and medicines follow.',
            false,
            [
                'emergency' => ['consultation', ServiceCharge::CONSULTATION, true],
                'laboratory' => [null, null, false],
                'pharmacy' => [null, null, false],
                'billing' => [null, null, false],
            ],
            [
                'emergency' => ['pharmacy' => true, 'laboratory' => false, 'billing' => false],
                'laboratory' => ['emergency' => true],
                'pharmacy' => ['billing' => true],
            ],
        ],
        'anc' => [
            'Antenatal care',
            'The antenatal visit: nursing review, routine investigations, supplements and payment.',
            false,
            [
                'anc' => ['nursing', null, true],
                'laboratory' => [null, null, false],
                'pharmacy' => [null, null, false],
                'billing' => [null, null, false],
            ],
            [
                'anc' => ['laboratory' => true, 'pharmacy' => false, 'billing' => false],
                'laboratory' => ['anc' => true, 'pharmacy' => false],
                'pharmacy' => ['billing' => true],
            ],
        ],
        'immunization' => [
            'Immunization',
            'The immunization clinic visit.',
            false,
            [
                'immunization' => ['nursing', null, true],
                'billing' => [null, null, false],
            ],
            [
                'immunization' => ['billing' => true],
            ],
        ],
    ];

    public function run(): void
    {
        $servicePoints = ServicePoint::query()->pluck('id', 'slug');
        $sort = 0;

        foreach ($this->workflows as $slug => [$name, $description, $isDefault, $stages, $transitions]) {
            $workflow = Workflow::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'is_default' => $isDefault, 'is_active' => true, 'sort_order' => $sort += 10],
            );

            $stageIds = [];
            $position = 0;

            foreach ($stages as $pointSlug => [$encounterType, $chargeCode, $isEntry]) {
                if (! isset($servicePoints[$pointSlug])) {
                    continue;
                }

                $stage = WorkflowStage::query()->updateOrCreate(
                    ['workflow_id' => $workflow->id, 'service_point_id' => $servicePoints[$pointSlug]],
                    ['position' => $position += 10, 'encounter_type' => $encounterType, 'charge_code' => $chargeCode, 'is_entry' => $isEntry],
                );

                $stageIds[$pointSlug] = $stage->id;
            }

            foreach ($transitions as $fromSlug => $targets) {
                $order = 0;

                foreach ($targets as $toSlug => $isDefaultMove) {
                    if (! isset($stageIds[$fromSlug], $stageIds[$toSlug])) {
                        continue;
                    }

                    WorkflowTransition::query()->updateOrCreate(
                        ['from_stage_id' => $stageIds[$fromSlug], 'to_stage_id' => $stageIds[$toSlug]],
                        ['workflow_id' => $workflow->id, 'is_default' => $isDefaultMove, 'sort_order' => $order += 10],
                    );
                }
            }
        }
    }
}

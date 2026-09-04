<?php

namespace Database\Seeders;

use App\Models\ServicePoint;
use Illuminate\Database\Seeder;

class ServicePointsSeeder extends Seeder
{
    /**
     * The service points patients can be routed through. `module_slug` decides
     * which staff may work the queue. Editable seed data — add or rename as the
     * facility's workflow evolves.
     *
     * @var array<int, array{slug: string, name: string, icon: string, module_slug: string, sort_order: int, description?: string, captures_vitals?: bool}>
     */
    protected array $servicePoints = [
        ['slug' => 'triage', 'name' => 'Triage / Vitals', 'icon' => 'Activity', 'module_slug' => 'nursing', 'sort_order' => 10, 'captures_vitals' => true, 'description' => 'Vitals and anthropometrics captured by nursing staff.'],
        ['slug' => 'consultation', 'name' => 'General Consultation', 'icon' => 'Stethoscope', 'module_slug' => 'clinical', 'sort_order' => 20, 'description' => 'Clinician consultation and diagnosis.'],
        ['slug' => 'anc', 'name' => 'Antenatal Care (ANC)', 'icon' => 'Baby', 'module_slug' => 'nursing', 'sort_order' => 30, 'captures_vitals' => true],
        ['slug' => 'family-planning', 'name' => 'Family Planning', 'icon' => 'HeartHandshake', 'module_slug' => 'nursing', 'sort_order' => 40],
        ['slug' => 'immunization', 'name' => 'Immunization', 'icon' => 'Syringe', 'module_slug' => 'nursing', 'sort_order' => 50, 'captures_vitals' => true],
        ['slug' => 'laboratory', 'name' => 'Laboratory', 'icon' => 'FlaskConical', 'module_slug' => 'laboratory', 'sort_order' => 60],
        ['slug' => 'pharmacy', 'name' => 'Pharmacy', 'icon' => 'Pill', 'module_slug' => 'pharmacy', 'sort_order' => 70],
        ['slug' => 'billing', 'name' => 'Billing / Cashier', 'icon' => 'ReceiptText', 'module_slug' => 'billing', 'sort_order' => 80],
    ];

    /**
     * Seed the service points.
     */
    public function run(): void
    {
        foreach ($this->servicePoints as $point) {
            ServicePoint::updateOrCreate(
                ['slug' => $point['slug']],
                [
                    'name' => $point['name'],
                    'icon' => $point['icon'],
                    'module_slug' => $point['module_slug'],
                    'captures_vitals' => $point['captures_vitals'] ?? false,
                    'sort_order' => $point['sort_order'],
                    'description' => $point['description'] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}

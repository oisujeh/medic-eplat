<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndModulesSeeder extends Seeder
{
    /**
     * The roles available in the system.
     *
     * @var array<int, array{slug: string, name: string, grants_all_modules?: bool}>
     */
    protected array $roles = [
        ['slug' => 'super-administrator', 'name' => 'Super Administrator', 'grants_all_modules' => true],
        ['slug' => 'chief-medical-director', 'name' => 'Chief Medical Director (CMD)'],
        ['slug' => 'physician', 'name' => 'Physician / Clinician'],
        ['slug' => 'nurse', 'name' => 'Nurse / Ward Staff'],
        ['slug' => 'records-officer', 'name' => 'Records Officer'],
        ['slug' => 'laboratory-staff', 'name' => 'Laboratory Staff'],
        ['slug' => 'pharmacy-staff', 'name' => 'Pharmacy Staff'],
        ['slug' => 'inventory-officer', 'name' => 'Inventory / Store Officer'],
        ['slug' => 'cashier', 'name' => 'Cashier / Billing Officer'],
    ];

    /**
     * The starter set of modules. These are editable seed data — add, rename or
     * remove modules as the application grows. `roles` lists the role slugs that
     * may access the module (the Super Administrator sees everything regardless).
     *
     * @var array<int, array{slug: string, name: string, icon: string, href?: string, sort_order: int, roles: array<int, string>}>
     */
    protected array $modules = [
        [
            'slug' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'LayoutGrid',
            'href' => '/dashboard', 'sort_order' => 0,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer', 'laboratory-staff', 'pharmacy-staff', 'inventory-officer', 'cashier'],
        ],
        [
            'slug' => 'patient-records', 'name' => 'Patient Records', 'icon' => 'FolderOpen',
            'href' => '/patients', 'sort_order' => 10,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'registration', 'name' => 'Patient Registration', 'icon' => 'UserPlus',
            'href' => '/registration', 'sort_order' => 15,
            'roles' => ['records-officer'],
        ],
        [
            'slug' => 'queues', 'name' => 'Service Queues', 'icon' => 'ListChecks',
            'href' => '/queues', 'sort_order' => 18,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer', 'laboratory-staff', 'pharmacy-staff', 'cashier'],
        ],
        [
            'slug' => 'appointments', 'name' => 'Appointments', 'icon' => 'CalendarDays',
            'href' => '/appointments', 'sort_order' => 19,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'clinical', 'name' => 'Clinical', 'icon' => 'Stethoscope',
            'href' => '/clinical', 'sort_order' => 20,
            'roles' => ['chief-medical-director', 'physician'],
        ],
        [
            'slug' => 'nursing', 'name' => 'Nursing / Ward', 'icon' => 'HeartPulse',
            'href' => '/nursing', 'sort_order' => 30,
            'roles' => ['nurse'],
        ],
        [
            'slug' => 'maternity', 'name' => 'Maternity', 'icon' => 'Baby',
            'href' => '/maternity', 'sort_order' => 33,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'surveillance', 'name' => 'Case Surveillance', 'icon' => 'Radar',
            'href' => '/surveillance', 'sort_order' => 34,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'referrals', 'name' => 'Referrals', 'icon' => 'Send',
            'href' => '/referrals', 'sort_order' => 36,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'admissions', 'name' => 'Admissions / Wards', 'icon' => 'BedDouble',
            'href' => '/admissions', 'sort_order' => 35,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'records-officer'],
        ],
        [
            'slug' => 'laboratory', 'name' => 'Laboratory', 'icon' => 'FlaskConical',
            'href' => '/laboratory', 'sort_order' => 40,
            'roles' => ['chief-medical-director', 'physician', 'laboratory-staff'],
        ],
        [
            'slug' => 'pharmacy', 'name' => 'Pharmacy', 'icon' => 'Pill',
            'href' => '/pharmacy', 'sort_order' => 50,
            'roles' => ['chief-medical-director', 'physician', 'nurse', 'pharmacy-staff'],
        ],
        [
            'slug' => 'inventory', 'name' => 'Inventory / Store', 'icon' => 'Package',
            'href' => '/inventory', 'sort_order' => 60,
            'roles' => ['chief-medical-director', 'inventory-officer', 'pharmacy-staff'],
        ],
        [
            'slug' => 'billing', 'name' => 'Billing / Cashier', 'icon' => 'ReceiptText',
            'href' => '/billing', 'sort_order' => 70,
            'roles' => ['chief-medical-director', 'cashier'],
        ],
        [
            'slug' => 'claims', 'name' => 'HMO / NHIA Claims', 'icon' => 'HandCoins',
            'href' => '/claims', 'sort_order' => 75,
            'roles' => ['chief-medical-director', 'cashier', 'records-officer'],
        ],
        [
            'slug' => 'reports', 'name' => 'Reports', 'icon' => 'ChartColumn',
            'href' => '/reports', 'sort_order' => 80,
            'roles' => ['chief-medical-director'],
        ],
        [
            'slug' => 'administration', 'name' => 'Administration', 'icon' => 'ShieldCheck',
            'href' => '/admin/users', 'sort_order' => 90,
            'roles' => [],
        ],
    ];

    /**
     * Seed the roles, modules and their access mapping.
     */
    public function run(): void
    {
        foreach ($this->roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'grants_all_modules' => $role['grants_all_modules'] ?? false,
                ],
            );
        }

        $roleIdsBySlug = Role::pluck('id', 'slug');

        foreach ($this->modules as $module) {
            $model = Module::updateOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'icon' => $module['icon'],
                    'href' => $module['href'] ?? null,
                    'sort_order' => $module['sort_order'],
                    'is_active' => true,
                ],
            );

            $model->roles()->sync(
                collect($module['roles'])
                    ->map(fn (string $slug) => $roleIdsBySlug[$slug] ?? null)
                    ->filter()
                    ->all(),
            );
        }
    }
}

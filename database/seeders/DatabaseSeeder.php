<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndModulesSeeder::class,
            MatchingRulesSeeder::class,
            ServicePointsSeeder::class,
            ServiceChargesSeeder::class,
            WorkflowsSeeder::class,
            WardsSeeder::class,
            PayersSeeder::class,
            IcdCodesSeeder::class,
            NotifiableDiseasesSeeder::class,
            LabCompendiumSeeder::class,
            PharmacyStockSeeder::class,
            TerminologySeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}

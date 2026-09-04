<?php

namespace Database\Seeders;

use App\Enums\PayerType;
use App\Models\Payer;
use Illuminate\Database\Seeder;

class PayersSeeder extends Seeder
{
    /**
     * The national scheme and the HMOs most facilities hold contracts with.
     * Tariff rules are left at their defaults for the facility to set.
     *
     * [code, name, type, drug co-pay %]
     *
     * @var array<int, array{0: string, 1: string, 2: PayerType, 3: float}>
     */
    protected array $payers = [
        ['NHIA', 'National Health Insurance Authority (NHIA)', PayerType::Nhia, 10],
        ['HYGEIA', 'Hygeia HMO', PayerType::Hmo, 0],
        ['RELIANCE', 'Reliance HMO', PayerType::Hmo, 0],
        ['AXA', 'AXA Mansard Health', PayerType::Hmo, 0],
        ['AVON', 'Avon HMO', PayerType::Hmo, 0],
        ['LEADWAY', 'Leadway Health', PayerType::Hmo, 0],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->payers as [$code, $name, $type, $drugCopay]) {
            Payer::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'drug_copay_percent' => $drugCopay,
                    'is_active' => true,
                ],
            );
        }
    }
}

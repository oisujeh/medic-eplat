<?php

namespace Database\Seeders;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class PharmacyStockSeeder extends Seeder
{
    /**
     * Starter pharmacy formulary.
     * [code, name, form, strength, unit, cost, selling, reorder, opening qty]
     *
     * @var array<int, array{0:string,1:string,2:string,3:string,4:string,5:float,6:float,7:int,8:int}>
     */
    protected array $items = [
        ['DRG-0001', 'Paracetamol', 'Tablet', '500mg', 'tablet', 3, 8, 100, 500],
        ['DRG-0002', 'Amoxicillin', 'Capsule', '500mg', 'capsule', 15, 30, 80, 300],
        ['DRG-0003', 'Amlodipine', 'Tablet', '5mg', 'tablet', 10, 25, 60, 240],
        ['DRG-0004', 'Metformin', 'Tablet', '500mg', 'tablet', 8, 18, 60, 240],
        ['DRG-0005', 'Lisinopril', 'Tablet', '10mg', 'tablet', 12, 28, 50, 200],
        ['DRG-0006', 'Artemether/Lumefantrine', 'Tablet', '20/120mg', 'tablet', 40, 90, 40, 180],
        ['DRG-0007', 'Omeprazole', 'Capsule', '20mg', 'capsule', 14, 32, 50, 200],
        ['DRG-0008', 'Ibuprofen', 'Tablet', '400mg', 'tablet', 5, 12, 80, 300],
        ['DRG-0009', 'Ceftriaxone', 'Injection', '1g', 'vial', 120, 250, 30, 60],
        ['DRG-0010', 'Metronidazole', 'Tablet', '400mg', 'tablet', 6, 15, 80, 300],
        ['DRG-0011', 'Hydrochlorothiazide', 'Tablet', '25mg', 'tablet', 9, 20, 40, 120],
        ['DRG-0012', 'ORS', 'Sachet', '20.5g', 'sachet', 20, 45, 40, 100],
    ];

    /**
     * Seed the pharmacy formulary with an opening batch each.
     */
    public function run(): void
    {
        foreach ($this->items as [$code, $name, $form, $strength, $unit, $cost, $selling, $reorder, $opening]) {
            $item = InventoryItem::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => InventoryCategory::Drug,
                    'form' => $form,
                    'strength' => $strength,
                    'unit' => $unit,
                    'cost_price' => $cost,
                    'selling_price' => $selling,
                    'reorder_level' => $reorder,
                    'is_active' => true,
                ],
            );

            // Give each item one opening batch (idempotent on re-seed).
            if ($item->batches()->doesntExist()) {
                $item->batches()->create([
                    'batch_number' => 'OPEN-'.$item->id,
                    'expiry_date' => now()->addYear()->toDateString(),
                    'quantity' => $opening,
                    'cost_price' => $cost,
                    'received_at' => now(),
                ]);
                $item->update(['quantity_on_hand' => $opening]);
            }
        }
    }
}

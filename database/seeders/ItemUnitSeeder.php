<?php

namespace Database\Seeders;

use App\Models\ItemUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unitNames = [
            'Ampule', 'Account', 'Assembly', 'Bag', 'Bar', 'Bot', 'Bottle', 'Booklet', 'Box', 'Bundle', 'Can', 'Capsule', 'Card',
            'Cart', 'Cartridge', 'Case', 'Cycle', 'Drum', 'Ft2', 'Gallon', 'GBq', 'Jar', 'Kg', 'Kilo', 'Kit', 'Kits', 'Liter', 'Loaf',
            'Lot', 'mCi', 'Meter', 'Month', 'Nebule', 'Pack', 'Pad', 'Pail', 'Pair', 'Pax', 'Pfs', 'Piece',
            'Plate', 'Rack', 'Ream', 'Roll', 'Sachet', 'Sack', 'Seat', 'Set', 'Sheet', 'Spool', 'System', 'Solution',
            'Tablet', 'Tank', 'Test', 'Tin', 'Tray', 'Tube', 'Unit', 'Vial', 'Inhaler', 'MDI', 'Prefilled Syringe', 'Syringe', 'Length', 'Letter', 'Infusion set',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        ItemUnit::truncate();

        $units = [];
        $now = now();

        foreach ($unitNames as $name) {
            $units[] = [
                'name' => $name,
                'code' => strtoupper(substr($name, 0, 3)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ItemUnit::insert($units);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}

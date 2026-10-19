<?php

namespace Database\Seeders;

use App\Models\InternalCategory;
use Illuminate\Database\Seeder;

class InternalCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('data/internal_categories.csv');

        if (file_exists($csvPath)) {
            $handle = fopen($csvPath, 'r');
            $header = fgetcsv($handle); // name,code

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2 && ! empty($row[0]) && ! empty($row[1])) {
                    $name = trim($row[0]);
                    $code = trim($row[1]);

                    InternalCategory::updateOrCreate(
                        ['code' => $code],
                        ['name' => $name]
                    );
                }
            }
            fclose($handle);
        } else {
            // Fallback inline data
            $categories = [
                ['name' => 'Books and Journals', 'code' => 'BO-JRN'],
                ['name' => 'Building and Construction', 'code' => 'BU-CON'],
                ['name' => 'Celebration and Events', 'code' => 'CE-EVE'],
                ['name' => 'Construction Supply', 'code' => 'CO-SUP'],
                ['name' => 'Drugs and Medicines', 'code' => 'DR-MED'],
                ['name' => 'Equipment Maintenance', 'code' => 'EQ-MAN'],
                ['name' => 'Food Supply', 'code' => 'FO-SUP'],
                ['name' => 'Food, Ingredients, and Grocery Items', 'code' => 'FI-GRC'],
                ['name' => 'Furniture and Fixture', 'code' => 'FU-FIX'],
                ['name' => 'Garden Supply', 'code' => 'GA-SUP'],
                ['name' => 'ICT Equipment', 'code' => 'IT-EQP'],
                ['name' => 'ICT Maintenance', 'code' => 'IT-MAN'],
                ['name' => 'Implant', 'code' => 'IMP'],
                ['name' => 'Janitorial Supply', 'code' => 'JA-SUP'],
                ['name' => 'Kitchen Equipment and Related Appliances', 'code' => 'KE-APP'],
                ['name' => 'Kitchenware, Consumables and Other Supplies', 'code' => 'KC-OTH'],
                ['name' => 'Laboratory Supply', 'code' => 'LA-SUP'],
                ['name' => 'Linen and Laundry Supply', 'code' => 'LL-SUP'],
                ['name' => 'Medical Equipment', 'code' => 'ME-EQP'],
                ['name' => 'Medical Equipment Maintenance', 'code' => 'ME-EQP-MAN'],
                ['name' => 'Medical Instrument', 'code' => 'ME-INS'],
                ['name' => 'Medical Supply', 'code' => 'ME-SUP'],
                ['name' => 'Office Supply', 'code' => 'OF-SUP'],
                ['name' => 'Other Equipment', 'code' => 'OT-EQP'],
                ['name' => 'Other Equipment Maintenance', 'code' => 'OT-EQP-MAN'],
                ['name' => 'Other Fees, Subscription, Processing', 'code' => 'OT-SUB'],
                ['name' => 'Other Supply', 'code' => 'OT-SUP'],
                ['name' => 'Other tools', 'code' => 'OT-TLS'],
                ['name' => 'Parts and Spare', 'code' => 'PA-SPR'],
                ['name' => 'Services and Consultancy', 'code' => 'SE-CON'],
                ['name' => 'Software and Subscription', 'code' => 'ST-SUB'],
                ['name' => 'Training Expenses', 'code' => 'TR-EXP'],
            ];

            foreach ($categories as $cat) {
                InternalCategory::updateOrCreate(
                    ['code' => $cat['code']],
                    ['name' => $cat['name']]
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'erp_item_code' => 'OFC-SUP-PP-01',
                'bizbox_item_code' => 'BZ-OFC-PPA4',
                'philgeps_code' => '14111507-PP-M01',
                'unspsc_code' => '14111507',
                'category_key' => 100765, // Printer or copier paper
                'item_description' => 'PAPER, MULTICOPY, A4',
                'generic_name' => 'Copy Paper',
                'brand' => 'Advance',
                'uom' => 'ream',
                'unit_price' => 204.67,
                'specifications' => '80gsm;A4, 210mm x 297mm;500 sheets per ream',
                'remarks' => 'Regular stock for administrative and clinical records',
            ],
            [
                'erp_item_code' => 'OFC-SUP-PP-02',
                'bizbox_item_code' => 'BZ-OFC-PPLG',
                'philgeps_code' => '14111507-PP-M02',
                'unspsc_code' => '14111507',
                'category_key' => 100765, // Printer or copier paper
                'item_description' => 'PAPER, MULTICOPY, LEGAL (8.5 x 14 INCHES)',
                'generic_name' => 'Copy Paper Legal',
                'brand' => 'Paper One',
                'uom' => 'ream',
                'unit_price' => 235.50,
                'specifications' => '80gsm;Legal size 216mm x 330mm;500 sheets per ream',
                'remarks' => 'Procurement for legal forms and hospital charts',
            ],
            [
                'erp_item_code' => 'DRG-MED-PC-01',
                'bizbox_item_code' => 'BZ-PHARM-PARA500',
                'philgeps_code' => '51381701-TAB-01',
                'unspsc_code' => '51381701',
                'category_key' => 106595, // Acetaminophen or paracetamol
                'item_description' => 'PARACETAMOL, 500MG, TABLET',
                'generic_name' => 'Paracetamol',
                'brand' => 'Biogesic / Generic',
                'uom' => 'tablet',
                'unit_price' => 1.75,
                'specifications' => '500mg active ingredient;USP grade;Blister pack of 10s',
                'remarks' => 'Essential medicine list (EML)',
            ],
            [
                'erp_item_code' => 'DRG-MED-AMX-01',
                'bizbox_item_code' => 'BZ-PHARM-AMX500',
                'philgeps_code' => '51281702-CAP-01',
                'unspsc_code' => '51281702',
                'category_key' => 181758, // Amoxicillin
                'item_description' => 'AMOXICILLIN, 500MG, CAPSULE',
                'generic_name' => 'Amoxicillin Trihydrate',
                'brand' => 'Generic',
                'uom' => 'capsule',
                'unit_price' => 3.20,
                'specifications' => '500mg Amoxicillin (as trihydrate);USP/BP certified;Box of 100 capsules',
                'remarks' => 'Oral antibiotic',
            ],
            [
                'erp_item_code' => 'MED-SUP-GLV-01',
                'bizbox_item_code' => 'BZ-MED-GLVM',
                'philgeps_code' => '42132203-GLV-M',
                'unspsc_code' => '42132203',
                'category_key' => 117213, // Medical exam or non surgical procedure gloves
                'item_description' => 'GLOVES, EXAMINATION, NITRILE, POWDER-FREE, MEDIUM',
                'generic_name' => 'Nitrile Exam Gloves',
                'brand' => 'Top Glove',
                'uom' => 'box',
                'unit_price' => 340.00,
                'specifications' => 'Medical grade nitrile;Powder-free;Size: Medium;Ambidextrous;100 pieces per box',
                'remarks' => 'Clinical and ward consumable',
            ],
            [
                'erp_item_code' => 'MED-SUP-SYR-01',
                'bizbox_item_code' => 'BZ-MED-SYR5ML',
                'philgeps_code' => '42142609-SYR-5M',
                'unspsc_code' => '42142609',
                'category_key' => 114944, // Medical syringes with needle
                'item_description' => 'SYRINGE, DISPOSABLE, WITH NEEDLE, 5ML, 21G X 1 1/2 INCH',
                'generic_name' => 'Hypodermic Syringe with Needle',
                'brand' => 'Terumo',
                'uom' => 'piece',
                'unit_price' => 8.50,
                'specifications' => '5ml volume capacity;Luer lock tip;Needle: 21G x 1.5 inch;Sterile, non-toxic, non-pyrogenic;Individually blister packed',
                'remarks' => 'Inpatient / Outpatient injection administration',
            ],
            [
                'erp_item_code' => 'MED-SUP-MSK-01',
                'bizbox_item_code' => 'BZ-MED-MSK3P',
                'philgeps_code' => '42131713-MSK-01',
                'unspsc_code' => '42131713',
                'category_key' => 114814, // Surgical isolation or surgical masks
                'item_description' => 'FACE MASK, SURGICAL, 3-PLY, EARLOOP, NON-WOVEN',
                'generic_name' => 'Surgical Mask',
                'brand' => 'Medicom',
                'uom' => 'box',
                'unit_price' => 125.00,
                'specifications' => '3-ply non-woven meltblown filter;BFE >= 99%;Elastic earloops;Pliable nose clip;50 pieces per box',
                'remarks' => 'Infection control standard issue',
            ],
            [
                'erp_item_code' => 'LAB-SUP-BCT-01',
                'bizbox_item_code' => 'BZ-LAB-EDTA3',
                'philgeps_code' => '41104107-BCT-LAV',
                'unspsc_code' => '41104107',
                'category_key' => 116687, // Vacuum blood collection tubes or containers
                'item_description' => 'BLOOD COLLECTION TUBE, VACUUM, K3 EDTA, 3ML, LAVENDER TOP',
                'generic_name' => 'EDTA Blood Tube',
                'brand' => 'BD Vacutainer',
                'uom' => 'pack',
                'unit_price' => 780.00,
                'specifications' => 'K3 EDTA anticoagulant;Draw volume: 3ml;Dimensions: 13x75mm;Lavender cap;Sterile interior;100 tubes per pack',
                'remarks' => 'For CBC and hematology laboratory sections',
            ],
            [
                'erp_item_code' => 'MED-SUP-ALC-01',
                'bizbox_item_code' => 'BZ-MED-ALC70P',
                'philgeps_code' => '51473016-ALC-500',
                'unspsc_code' => '51473016',
                'category_key' => 184251, // Acetone or alcohol based antiseptics
                'item_description' => 'ALCOHOL, ETHYL, 70% ISOPROPYL/ETHYL SOLUTION, 500ML',
                'generic_name' => 'Antiseptic Alcohol 70%',
                'brand' => 'Green Cross',
                'uom' => 'bottle',
                'unit_price' => 68.00,
                'specifications' => '70% ethyl alcohol solution;With moisturizer;500ml plastic pump dispenser bottle;FDA approved',
                'remarks' => 'Hand hygiene and surface antisepsis',
            ],
            [
                'erp_item_code' => 'IT-EQP-DESK-01',
                'bizbox_item_code' => 'BZ-IT-PC-I7',
                'philgeps_code' => '43211507-PC-01',
                'unspsc_code' => '43211507',
                'category_key' => 104847, // Desktop computers
                'item_description' => 'DESKTOP COMPUTER, CORE I7, 16GB RAM, 512GB SSD, WITH 23.8 INCH MONITOR',
                'generic_name' => 'Personal Workstation',
                'brand' => 'Dell OptiPlex',
                'uom' => 'set',
                'unit_price' => 48500.00,
                'specifications' => 'Intel Core i7 13th Gen;16GB DDR5 RAM;512GB NVMe SSD;23.8" FHD IPS Monitor;USB Keyboard & Optical Mouse;Windows 11 Pro 64-bit;3-year on-site warranty',
                'remarks' => 'For hospital HIS / Bizbox terminal deployment',
            ],
        ];

        foreach ($products as $prod) {
            Product::updateOrCreate(
                ['erp_item_code' => $prod['erp_item_code']],
                $prod
            );
        }

        $this->command->info('Successfully seeded ' . count($products) . ' sample procurement catalogue products.');
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service responsible for Product CSV Export and Import.
 * Following SRP: Isolates data conversion and stream I/O from controller logic.
 */
class ProductExportImportService
{
    /**
     * Export products to a CSV stream matching client template format.
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $query = Product::with('categoryRelation');

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (!empty($filters['category_key'])) {
            $query->filterByCategory($filters['category_key']);
        }

        $products = $query->orderBy('item_description', 'asc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ZCMC_Product_Catalogue_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($products) {
            $file = fopen('php://output', 'w');

            // Line 1: Header annotations as per client's CSV format
            fputcsv($file, ['', '', '', '', '', '', 'Unit of Measure', 'Unit Price', 'Technical Specifications (Separate each with a semicolon ;)']);

            // Line 2: Standard column headers
            fputcsv($file, [
                'id',
                'erp_item_code',
                'internal_category_code',
                'bizbox_item_code',
                'philgeps_code',
                'unspsc_code',
                'noun',
                'type',
                'material_composition',
                'key_attributes',
                'size',
                'net_content',
                'pack',
                'item description',
                'uom',
                'unit_price',
                'other_specs',
                'internal_category_name',
                'category_group',
                'category',
                'subcategory',
            ]);

            foreach ($products as $p) {
                // Dynamically resolve category levels from Category hierarchy
                fputcsv($file, [
                    $p->id,
                    $p->erp_item_code,
                    $p->internal_category_code,
                    $p->bizbox_item_code,
                    $p->philgeps_code,
                    $p->unspsc_code,
                    $p->noun,
                    $p->type,
                    $p->material_composition,
                    $p->key_attributes,
                    $p->size,
                    $p->net_content,
                    $p->pack,
                    $p->item_description,
                    $p->uom,
                    number_format($p->unit_price, 2, '.', ''),
                    $p->other_specs,
                    $p->internalCategory ? $p->internalCategory->name : '',
                    $p->category_group,
                    $p->category,
                    $p->subcategory,
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }

    /**
     * Import products from CSV.
     */
    public function importCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $imported = 0;
        $updated = 0;
        $errors = [];
        $headerFound = false;
        $rowIndex = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;

            if (!$headerFound) {
                if (isset($row[5]) && stripos($row[5], 'item description') !== false) {
                    $headerFound = true;
                }
                continue;
            }

            if (empty($row[5])) {
                continue;
            }

            try {
                $erpCode = !empty($row[1]) ? trim($row[1]) : ('ZCMC-' . strtoupper(uniqid()));
                $desc = strtoupper(trim($row[5]));
                $uom = !empty($row[6]) ? trim($row[6]) : 'piece';
                $price = !empty($row[7]) ? (float) str_replace([',', ' '], '', $row[7]) : 0.00;
                $unspscCode = !empty($row[4]) ? trim($row[4]) : null;

                // Try to find matching category by unspsc code or title
                $categoryKey = null;
                if ($unspscCode) {
                    $cat = \App\Models\Category::where('code', $unspscCode)->first();
                    if ($cat) {
                        $categoryKey = $cat->key;
                    }
                }
                if (!$categoryKey && !empty($row[11])) {
                    $cat = \App\Models\Category::where('title', trim($row[11]))->first();
                    if ($cat) {
                        $categoryKey = $cat->key;
                    }
                }

                $product = Product::updateOrCreate(
                    ['erp_item_code' => $erpCode],
                    [
                        'bizbox_item_code' => !empty($row[2]) ? trim($row[2]) : null,
                        'philgeps_code' => !empty($row[3]) ? trim($row[3]) : null,
                        'unspsc_code' => $unspscCode,
                        'category_key' => $categoryKey,
                        'item_description' => $desc,
                        'uom' => $uom,
                        'unit_price' => $price,
                        'specifications' => !empty($row[8]) ? trim($row[8]) : null,
                    ]
                );

                if ($product->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowIndex}: " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
}

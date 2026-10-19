<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service responsible for Product business logic.
 * Following SRP: Encapsulates creation, updates, and filtering of products.
 */
class ProductService
{
    /**
     * Retrieve paginated products with applied filters and flexible hierarchy search.
     */
    public function listProducts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::with(['categoryRelation', 'internalCategory']);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['category_key'])) {
            $query->filterByCategory($filters['category_key']);
        }

        if (! empty($filters['internal_category_id'])) {
            $query->where('internal_category_id', $filters['internal_category_id']);
        }

        if (! empty($filters['uom']) && strtolower($filters['uom']) !== 'all') {
            $query->whereRaw('LOWER(uom) = ?', [strtolower($filters['uom'])]);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'erp_item_code', 'item_description', 'unit_price', 'uom', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Generate the next sequential ERP Item Code based on:
     * [category->code]-[internal category code]-[unique identifier number starting at 01]
     */
    public function generateNextErpCode(string $categoryCode, string $internalCatCode): string
    {
        $prefix = "{$categoryCode}-{$internalCatCode}-";
        $escapedPrefix = str_replace(['%', '_'], ['\\%', '\\_'], $prefix);

        $existingCodes = Product::where('erp_item_code', 'like', $escapedPrefix.'%')
            ->pluck('erp_item_code');

        $maxSeq = 0;
        foreach ($existingCodes as $code) {
            $suffix = substr($code, strlen($prefix));
            if (is_numeric($suffix)) {
                $val = (int) $suffix;
                if ($val > $maxSeq) {
                    $maxSeq = $val;
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        $seqStr = str_pad((string) $nextSeq, 2, '0', STR_PAD_LEFT);

        return "{$prefix}{$seqStr}";
    }

    /**
     * Create a new catalogue product.
     */
    public function createProduct(array $data): Product
    {
        // Build standard item description from structured attributes if provided
        $builtDesc = Product::buildStandardName($data);
        if (! empty($builtDesc)) {
            $data['item_description'] = $builtDesc;
        } else {
            $data['item_description'] = strtoupper(trim($data['item_description'] ?? ''));
        }

        // Auto-derive UNSPSC code from category if not explicitly provided
        $categoryCode = ! empty($data['unspsc_code']) ? $data['unspsc_code'] : null;
        if (! empty($data['category_key'])) {
            $category = Category::find($data['category_key']);
            if ($category && ! empty($category->code)) {
                $data['unspsc_code'] = $category->code;
                $categoryCode = $category->code;
            }
        }

        // Resolve internal category code from relationship
        $internalCatCode = null;
        if (! empty($data['internal_category_id'])) {
            $internalCat = InternalCategory::find($data['internal_category_id']);
            if ($internalCat) {
                $internalCatCode = $internalCat->code;
            }
        }

        // Auto-generate unique ERP item code: [category->code]-[internal category code]-[seq starting at 01]
        if (! empty($categoryCode) && ! empty($internalCatCode)) {
            $data['erp_item_code'] = $this->generateNextErpCode($categoryCode, $internalCatCode);
        } elseif (empty($data['erp_item_code'])) {
            $prefix = 'ZCMC-';
            $catCode = ! empty($data['unspsc_code']) ? substr($data['unspsc_code'], 0, 4) : 'GEN';
            $data['erp_item_code'] = $prefix.$catCode.'-'.strtoupper(substr(uniqid(), -6));
        }

        $data['unit_price'] = $data['unit_price'] ?? 0.00;

        $product = Product::create($data);

        // Record initial price entry in product_price_histories
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'old_price' => null,
            'new_price' => (float) $product->unit_price,
            'price_change' => 0,
            'percentage_change' => 0,
            'reason' => ! empty($data['price_change_reason']) ? $data['price_change_reason'] : 'Initial catalogue price',
            'effective_date' => now(),
        ]);

        // Record Activity Log
        ActivityLog::record(
            'CREATED',
            "Product added to catalogue: {$product->item_description} ({$product->erp_item_code})",
            'Product',
            $product->id,
            $product->erp_item_code,
            null,
            $product->only([
                'erp_item_code', 'item_description', 'noun', 'type', 'material_composition',
                'key_attributes', 'size', 'uom', 'bizbox_item_code', 'philgeps_code',
                'unspsc_code', 'category_key', 'internal_category_id', 'other_specs',
            ])
        );

        return $product->load(['categoryRelation', 'internalCategory', 'priceHistories']);
    }

    /**
     * Update an existing catalogue product.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // Snapshot old values before update
        $oldValues = $product->only([
            'erp_item_code', 'item_description', 'noun', 'type', 'material_composition',
            'key_attributes', 'size', 'uom', 'bizbox_item_code', 'philgeps_code',
            'unspsc_code', 'category_key', 'internal_category_id', 'other_specs', 'tech_specs_pdf_path',
        ]);

        // Build standard item description from structured attributes if provided
        $builtDesc = Product::buildStandardName($data);
        if (! empty($builtDesc)) {
            $data['item_description'] = $builtDesc;
        } elseif (isset($data['item_description'])) {
            $data['item_description'] = strtoupper(trim($data['item_description']));
        }

        if (! empty($data['category_key']) && $data['category_key'] != $product->category_key) {
            if (empty($data['unspsc_code'])) {
                $category = Category::find($data['category_key']);
                if ($category && ! empty($category->code)) {
                    $data['unspsc_code'] = $category->code;
                }
            }
        }

        // Protect ERP item code: do not allow user overwrite if it's already generated
        if (isset($data['erp_item_code']) && ! empty($product->erp_item_code)) {
            unset($data['erp_item_code']);
        }

        // Track price change if unit_price was modified
        if (isset($data['unit_price'])) {
            $oldPrice = (float) $product->unit_price;
            $newPrice = (float) $data['unit_price'];

            if (abs($oldPrice - $newPrice) > 0.001) {
                $change = $newPrice - $oldPrice;
                $pct = $oldPrice > 0 ? round(($change / $oldPrice) * 100, 2) : 0;

                ProductPriceHistory::create([
                    'product_id' => $product->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'price_change' => $change,
                    'percentage_change' => $pct,
                    'reason' => ! empty($data['price_change_reason']) ? $data['price_change_reason'] : 'Unit price adjustment / Inflation update',
                    'effective_date' => now(),
                ]);
            }
        }

        $product->update($data);
        $fresh = $product->fresh();

        $newValues = $fresh->only([
            'erp_item_code', 'item_description', 'noun', 'type', 'material_composition',
            'key_attributes', 'size', 'uom', 'bizbox_item_code', 'philgeps_code',
            'unspsc_code', 'category_key', 'internal_category_id', 'other_specs', 'tech_specs_pdf_path',
        ]);

        // Diff old and new values to capture only changed fields
        $changedOld = [];
        $changedNew = [];
        foreach ($newValues as $key => $val) {
            if (($oldValues[$key] ?? null) != $val) {
                $changedOld[$key] = $oldValues[$key] ?? null;
                $changedNew[$key] = $val;
            }
        }

        if (! empty($changedNew)) {
            ActivityLog::record(
                'UPDATED',
                "Product updated: {$fresh->item_description} ({$fresh->erp_item_code})",
                'Product',
                $fresh->id,
                $fresh->erp_item_code,
                $changedOld,
                $changedNew
            );
        }

        return $fresh->load(['categoryRelation', 'internalCategory', 'priceHistories']);
    }

    /**
     * Delete a catalogue product.
     */
    public function deleteProduct(Product $product): bool
    {
        $snapshot = $product->only([
            'id', 'erp_item_code', 'item_description', 'noun', 'bizbox_item_code', 'philgeps_code', 'uom',
        ]);

        ActivityLog::record(
            'DELETED',
            "Product deleted from catalogue: {$product->item_description} ({$product->erp_item_code})",
            'Product',
            $product->id,
            $product->erp_item_code,
            $snapshot,
            null
        );

        return $product->delete();
    }
}

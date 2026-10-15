<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\InternalCategory;
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

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['category_key'])) {
            $query->filterByCategory($filters['category_key']);
        }

        if (!empty($filters['internal_category_id'])) {
            $query->where('internal_category_id', $filters['internal_category_id']);
        }

        if (!empty($filters['uom'])) {
            $query->where('uom', $filters['uom']);
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

        $existingCodes = Product::where('erp_item_code', 'like', $escapedPrefix . '%')
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
        $data['item_description'] = strtoupper(trim($data['item_description']));

        // Auto-derive UNSPSC code from category if not explicitly provided
        $categoryCode = !empty($data['unspsc_code']) ? $data['unspsc_code'] : null;
        if (!empty($data['category_key'])) {
            $category = Category::find($data['category_key']);
            if ($category && !empty($category->code)) {
                $data['unspsc_code'] = $category->code;
                $categoryCode = $category->code;
            }
        }

        // Resolve internal category code
        if (!empty($data['internal_category_id'])) {
            $internalCat = InternalCategory::find($data['internal_category_id']);
            if ($internalCat) {
                $data['internal_category_code'] = $internalCat->code;
            }
        }

        // Auto-generate unique ERP item code: [category->code]-[internal category code]-[seq starting at 01]
        if (!empty($categoryCode) && !empty($data['internal_category_code'])) {
            $data['erp_item_code'] = $this->generateNextErpCode($categoryCode, $data['internal_category_code']);
        } elseif (empty($data['erp_item_code'])) {
            $prefix = 'ZCMC-';
            $catCode = !empty($data['unspsc_code']) ? substr($data['unspsc_code'], 0, 4) : 'GEN';
            $data['erp_item_code'] = $prefix . $catCode . '-' . strtoupper(substr(uniqid(), -6));
        }

        $data['unit_price'] = $data['unit_price'] ?? 0.00;

        $product = Product::create($data);
        return $product->load(['categoryRelation', 'internalCategory']);
    }

    /**
     * Update an existing catalogue product.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $data['item_description'] = strtoupper(trim($data['item_description']));

        if (!empty($data['category_key']) && $data['category_key'] != $product->category_key) {
            if (empty($data['unspsc_code'])) {
                $category = Category::find($data['category_key']);
                if ($category && !empty($category->code)) {
                    $data['unspsc_code'] = $category->code;
                }
            }
        }

        if (!empty($data['internal_category_id'])) {
            $internalCat = InternalCategory::find($data['internal_category_id']);
            if ($internalCat) {
                $data['internal_category_code'] = $internalCat->code;
            }
        }

        // Protect ERP item code: do not allow user overwrite if it's already generated
        if (isset($data['erp_item_code']) && !empty($product->erp_item_code)) {
            unset($data['erp_item_code']);
        }

        $product->update($data);
        return $product->fresh()->load(['categoryRelation', 'internalCategory']);
    }

    /**
     * Delete a catalogue product.
     */
    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }
}

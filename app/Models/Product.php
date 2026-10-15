<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_key',
        'internal_category_id',
        'internal_category_code',
        'erp_item_code',
        'bizbox_item_code',
        'philgeps_code',
        'unspsc_code',
        'item_description',
        'generic_name',
        'brand',
        'uom',
        'unit_price',
        'specifications',
        'remarks',
    ];

    protected $casts = [
        'unit_price' => 'float',
    ];

    /**
     * Associated UNSPSC / Catalogue Category.
     */
    public function categoryRelation()
    {
        return $this->belongsTo(Category::class, 'category_key', 'key');
    }

    /**
     * Associated Internal Institution Category.
     */
    public function internalCategory()
    {
        return $this->belongsTo(InternalCategory::class, 'internal_category_id');
    }

    /**
     * Scope to search products by keyword across description, item codes, brand, etc.
     */
    public function scopeSearch($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('item_description', 'like', "%{$term}%")
              ->orWhere('erp_item_code', 'like', "%{$term}%")
              ->orWhere('bizbox_item_code', 'like', "%{$term}%")
              ->orWhere('philgeps_code', 'like', "%{$term}%")
              ->orWhere('unspsc_code', 'like', "%{$term}%")
              ->orWhere('generic_name', 'like', "%{$term}%")
              ->orWhere('brand', 'like', "%{$term}%")
              ->orWhere('specifications', 'like', "%{$term}%")
              ->orWhereHas('categoryRelation', function ($catQ) use ($term) {
                  $catQ->where('title', 'like', "%{$term}%")
                       ->orWhere('code', 'like', "%{$term}%");
              });
        });
    }

    protected $appends = [
        'category_group',
        'category',
        'subcategory',
        'category_hierarchy',
        'full_category_path',
    ];

    /**
     * Dynamically resolve top-level category group (Level 0 Segment).
     */
    public function getCategoryGroupAttribute(): ?string
    {
        $hierarchy = $this->category_hierarchy;
        return !empty($hierarchy) ? $hierarchy[0]['title'] : null;
    }

    /**
     * Dynamically resolve intermediate parent category (e.g. Level 1 or 2).
     */
    public function getCategoryAttribute(): ?string
    {
        $hierarchy = $this->category_hierarchy;
        $count = count($hierarchy);
        if ($count > 2) {
            return $hierarchy[$count - 2]['title'] ?? null;
        } elseif ($count === 2) {
            return $hierarchy[1]['title'] ?? null;
        }
        return !empty($hierarchy) ? $hierarchy[0]['title'] : null;
    }

    /**
     * Dynamically resolve subcategory (the assigned specific category node title).
     */
    public function getSubcategoryAttribute(): ?string
    {
        return $this->categoryRelation ? $this->categoryRelation->title : null;
    }

    /**
     * Get structured breakdown of all category levels for this product.
     */
    public function getCategoryHierarchyAttribute(): array
    {
        if (!$this->categoryRelation) {
            return [];
        }

        $hierarchy = $this->categoryRelation->getAncestors();
        $hierarchy[] = [
            'key' => $this->categoryRelation->key,
            'code' => $this->categoryRelation->code,
            'title' => $this->categoryRelation->title,
            'level' => $this->categoryRelation->level,
        ];

        return $hierarchy;
    }

    /**
     * Get formatted full breadcrumb trail.
     */
    public function getFullCategoryPathAttribute(): ?string
    {
        return $this->categoryRelation ? $this->categoryRelation->full_path : null;
    }

    /**
     * Scope to filter by category key at ANY level of the hierarchy.
     * If user filters by Level 0 (e.g. Segment "Raw Materials"), it matches
     * all products attached to that segment OR ANY of its descendants (family, class, commodity, subcategory)!
     */
    public function scopeFilterByCategory($query, $categoryKey)
    {
        if (empty($categoryKey)) {
            return $query;
        }

        $category = \App\Models\Category::find($categoryKey);
        if ($category) {
            $descendantKeys = $category->getAllDescendantKeys();
            return $query->whereIn('category_key', $descendantKeys);
        }

        return $query->where('category_key', $categoryKey);
    }
}

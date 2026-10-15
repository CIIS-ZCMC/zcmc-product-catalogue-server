<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_key',
        'internal_category_id',
        'erp_item_code',
        'bizbox_item_code',
        'philgeps_code',
        'unspsc_code',
        'noun',
        'type',
        'material_composition',
        'key_attributes',
        'size',
        'item_description',
        'uom',
        'unit_price',
        'other_specs',
        'tech_specs_pdf_path',
    ];

    protected $casts = [
        'unit_price' => 'float',
    ];

    /**
     * Build the standard item description from structured attributes:
     * [Noun], [Type], [Material / Composition], [Key Attributes], [Size]
     */
    public static function buildStandardName(array $fields): string
    {
        $orderedParts = [
            $fields['noun'] ?? null,
            $fields['type'] ?? null,
            $fields['material_composition'] ?? null,
            $fields['key_attributes'] ?? null,
            $fields['size'] ?? null,
        ];

        $cleaned = [];
        foreach ($orderedParts as $part) {
            if ($part !== null && trim($part) !== '') {
                $cleaned[] = strtoupper(trim($part));
            }
        }

        return implode(', ', $cleaned);
    }

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
     * Unit price change history tracking (inflation & price movements over time).
     */
    public function priceHistories()
    {
        return $this->hasMany(ProductPriceHistory::class)->orderBy('effective_date', 'desc');
    }

    /**
     * Scope to search products by keyword across description, structured fields, codes, etc.
     */
    public function scopeSearch($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('item_description', 'like', "%{$term}%")
              ->orWhere('noun', 'like', "%{$term}%")
              ->orWhere('type', 'like', "%{$term}%")
              ->orWhere('material_composition', 'like', "%{$term}%")
              ->orWhere('key_attributes', 'like', "%{$term}%")
              ->orWhere('size', 'like', "%{$term}%")
              ->orWhere('other_specs', 'like', "%{$term}%")
              ->orWhere('erp_item_code', 'like', "%{$term}%")
              ->orWhere('bizbox_item_code', 'like', "%{$term}%")
              ->orWhere('philgeps_code', 'like', "%{$term}%")
              ->orWhere('unspsc_code', 'like', "%{$term}%")
              ->orWhereHas('categoryRelation', function ($catQ) use ($term) {
                  $catQ->where('title', 'like', "%{$term}%")
                       ->orWhere('code', 'like', "%{$term}%");
              })
              ->orWhereHas('internalCategory', function ($intQ) use ($term) {
                  $intQ->where('name', 'like', "%{$term}%")
                       ->orWhere('code', 'like', "%{$term}%");
              });
        });
    }

    protected $appends = [
        'internal_category_code',
        'tech_specs_pdf_url',
        'category_group',
        'category',
        'subcategory',
        'category_hierarchy',
        'full_category_path',
    ];

    /**
     * Publicly accessible URL for uploaded technical specifications PDF.
     */
    public function getTechSpecsPdfUrlAttribute(): ?string
    {
        if (!$this->tech_specs_pdf_path) {
            return null;
        }
        return asset('storage/' . $this->tech_specs_pdf_path);
    }

    /**
     * Dynamically resolve internal category code from internalCategory relation.
     */
    public function getInternalCategoryCodeAttribute(): ?string
    {
        return $this->internalCategory ? $this->internalCategory->code : null;
    }

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

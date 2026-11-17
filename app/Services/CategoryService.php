<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service responsible for managing Category hierarchy and tree operations.
 * Following SRP: Handles all Category business logic.
 */
class CategoryService
{
    /**
     * Get paginated or filtered list of categories.
     */
    public function listCategories(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Category::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (array_key_exists('parent_key', $filters)) {
            $parentKey = $filters['parent_key'];
            if ($parentKey === 'null' || $parentKey === null || $parentKey === '') {
                $query->whereNull('parent_key');
            } else {
                $query->where('parent_key', $parentKey);
            }
        } elseif (! empty($filters['roots_only'])) {
            $query->whereNull('parent_key');
        }

        if (isset($filters['level']) && $filters['level'] !== '') {
            $query->where('level', $filters['level']);
        }

        $query->withCount(['children', 'products']);

        return $query->orderBy('key', 'asc')->paginate($perPage);
    }

    /**
     * Fast autocomplete / search for category selection.
     */
    public function search(string $term, int $limit = 30, ?int $level = null): array
    {
        if (strlen($term) < 2) {
            return [];
        }

        $query = Category::search($term);

        if ($level !== null) {
            $query->where('level', $level);
        }

        return $query
            ->with('parent')
            ->limit($limit)
            ->get()
            ->map(function (Category $cat) {
                return [
                    'key' => $cat->key,
                    'code' => $cat->code,
                    'title' => $cat->title,
                    'level' => $cat->level,
                    'parent_key' => $cat->parent_key,
                    'parent_title' => $cat->parent ? $cat->parent->title : null,
                    'full_path' => $cat->full_path,
                    'definition' => $cat->definition,
                ];
            })
            ->toArray();
    }

    /**
     * Search specifically for Commodities (Level 3), with their immediate parent relations resolved.
     */
    public function searchCommodities(string $term = '', int $limit = 40): array
    {
        $query = Category::where('level', 3);

        if (! empty($term)) {
            $query->search($term);
        } else {
            // Curated initial suggestions for hospital procurement
            $query->whereIn('code', [
                '14111507', // Printer or copier paper
                '42132203', // Nitrile medical exam gloves
                '42132205', // Latex Surgical gloves
                '42142609', // Medical syringes with needle
                '51381701', // Acetaminophen or paracetamol
                '51281702', // Amoxicillin
                '42131713', // ASTM rated surgical masks
                '41104107', // Vacuum blood collection tubes
                '51473016', // Alcohol based antiseptics
                '43211507', // Desktop computer
                '44121908', // Ballpoint pen ink refill
                '42142523', // Hypodermic needle
                '42132210', // Chemotherapy exam gloves
                '42204005', // Medical radiological shielding gloves
                '42295458', // Drying or powdering equipment
            ]);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(function (Category $cat) {
                $ancestors = $cat->getAncestors();
                $segment = $ancestors[0] ?? null;
                $family = $ancestors[1] ?? null;
                $class = $ancestors[2] ?? null;

                return [
                    'key' => $cat->key,
                    'code' => $cat->code,
                    'title' => $cat->title,
                    'level' => 3,
                    'definition' => $cat->definition,
                    'full_path' => $cat->full_path,
                    'relations' => [
                        'segment' => $segment ? ($segment['title'] ?? null) : null,
                        'segment_code' => $segment ? ($segment['code'] ?? null) : null,
                        'family' => $family ? ($family['title'] ?? null) : null,
                        'family_code' => $family ? ($family['code'] ?? null) : null,
                        'class' => $class ? ($class['title'] ?? null) : null,
                        'class_code' => $class ? ($class['code'] ?? null) : null,
                        'commodity' => $cat->title,
                        'commodity_code' => $cat->code,
                    ],
                    'hierarchy' => array_merge($ancestors, [
                        [
                            'key' => $cat->key,
                            'code' => $cat->code,
                            'title' => $cat->title,
                            'level' => 3,
                        ],
                    ]),
                ];
            })
            ->toArray();
    }

    /**
     * Get top-level root segments (Level 0).
     */
    public function getRoots(): Collection
    {
        return Category::roots()
            ->withCount(['children', 'products'])
            ->orderBy('code', 'asc')
            ->get();
    }

    /**
     * Get immediate children for a specific parent key.
     */
    public function getChildren(int $parentKey): Collection
    {
        return Category::where('parent_key', $parentKey)
            ->withCount(['children', 'products'])
            ->orderBy('title', 'asc')
            ->get();
    }

    /**
     * Retrieve category by key with ancestors and children.
     */
    public function getCategoryDetails(int $key): array
    {
        $category = Category::withCount(['children', 'products'])
            ->with(['parent', 'children' => function ($q) {
                $q->withCount(['children', 'products'])->limit(50);
            }])
            ->findOrFail($key);

        return [
            'category' => $category,
            'ancestors' => $category->getAncestors(),
            'full_path' => $category->full_path,
        ];
    }

    /**
     * Create a new category node with automatic key and level.
     */
    public function createCategory(array $data): Category
    {
        $maxKey = (int) Category::max('key');
        $newKey = max($maxKey + 1, 9000000);

        return Category::create([
            'key' => $data['key'] ?? $newKey,
            'parent_key' => $data['parent_key'] ?? null,
            'code' => $data['code'] ?? null,
            'title' => $data['title'],
        ]);
    }

    /**
     * Update an existing category node.
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->update([
            'title' => $data['title'],
            'code' => $data['code'] ?? $category->code,
            'parent_key' => $data['parent_key'] ?? null,
        ]);

        return $category->fresh();
    }

    /**
     * Delete category with validation.
     */
    public function deleteCategory(Category $category): void
    {
        $category->loadCount(['children', 'products']);

        if ($category->children_count > 0) {
            throw new \DomainException("Cannot delete this category because it has {$category->children_count} child categories.");
        }

        if ($category->products_count > 0) {
            throw new \DomainException("Cannot delete this category because it has {$category->products_count} associated products.");
        }

        $category->delete();
    }
}

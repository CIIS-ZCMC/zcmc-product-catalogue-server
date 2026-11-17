<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller handling Category HTTP requests.
 * Following SRP: Responsible solely for HTTP response orchestration.
 * Following DIP: Depends on CategoryService abstraction injected via constructor.
 */
class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * List categories with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(5, min(100, $perPage));

        $categories = $this->categoryService->listCategories($request->all(), $perPage);

        return response()->json($categories);
    }

    /**
     * Autocomplete / search endpoint for category picker.
     */
    public function search(Request $request): JsonResponse
    {
        $term = (string) $request->input('q', '');
        $limit = (int) $request->input('limit', 30);
        $level = $request->has('level') && $request->input('level') !== '' ? (int) $request->input('level') : null;

        $results = $this->categoryService->search($term, $limit, $level);

        return response()->json($results);
    }

    /**
     * Search specifically for Commodities (Level 3), with resolved parent hierarchy.
     */
    public function commodities(Request $request): JsonResponse
    {
        $term = (string) $request->input('q', '');
        $limit = (int) $request->input('limit', 40);

        $results = $this->categoryService->searchCommodities($term, $limit);

        return response()->json($results);
    }

    /**
     * Get root segments (Level 0) for progressive drilldown navigation.
     */
    public function roots(): JsonResponse
    {
        return response()->json($this->categoryService->getRoots());
    }

    /**
     * Get immediate children of a specific category for cascaded selection.
     */
    public function children(int $key): JsonResponse
    {
        return response()->json($this->categoryService->getChildren($key));
    }

    /**
     * Display a specific category with ancestors and children.
     */
    public function show(int $key): JsonResponse
    {
        return response()->json($this->categoryService->getCategoryDetails($key));
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory($request->validated());

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category,
        ], 201);
    }

    /**
     * Update an existing category.
     */
    public function update(UpdateCategoryRequest $request, int $key): JsonResponse
    {
        $category = Category::findOrFail($key);
        $updated = $this->categoryService->updateCategory($category, $request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $updated,
        ]);
    }

    /**
     * Remove a category.
     */
    public function destroy(int $key): JsonResponse
    {
        $category = Category::findOrFail($key);

        try {
            $this->categoryService->deleteCategory($category);

            return response()->json([
                'message' => 'Category deleted successfully.',
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

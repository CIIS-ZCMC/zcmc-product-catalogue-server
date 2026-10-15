<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\UniformNamingService;
use App\Services\ProductExportImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller handling Product Catalogue operations.
 * Following SRP: Coordinates HTTP layer.
 * Following DIP: Injects ProductService, UniformNamingService, and ProductExportImportService.
 */
class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected UniformNamingService $namingService,
        protected ProductExportImportService $exportImportService
    ) {}

    /**
     * List products with pagination, search, and category tree filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(5, min(100, $perPage));

        $products = $this->productService->listProducts($request->all(), $perPage);

        return response()->json($products);
    }

    /**
     * Retrieve single product with full category path.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['categoryRelation', 'internalCategory'])->findOrFail($id);

        return response()->json([
            'product' => $product,
            'category_path' => $product->full_category_path,
            'category_hierarchy' => $product->category_hierarchy,
        ]);
    }

    /**
     * Preview next sequential ERP item code based on category code and internal category code.
     */
    public function nextErpCode(Request $request): JsonResponse
    {
        $categoryCode = $request->query('category_code');
        $categoryKey = $request->query('category_key');
        $internalCatCode = $request->query('internal_category_code');
        $internalCatId = $request->query('internal_category_id');

        // Resolve category code from category_key if not provided directly
        if (empty($categoryCode) && !empty($categoryKey)) {
            $cat = \App\Models\Category::find($categoryKey);
            if ($cat) {
                $categoryCode = $cat->code;
            }
        }

        if (!$internalCatCode && $internalCatId) {
            $cat = \App\Models\InternalCategory::find($internalCatId);
            if ($cat) {
                $internalCatCode = $cat->code;
            }
        }

        if (empty($categoryCode) || empty($internalCatCode)) {
            return response()->json([
                'erp_item_code' => '',
                'message' => 'Both category code and internal category code are required to generate ERP code.',
            ]);
        }

        $code = $this->productService->generateNextErpCode($categoryCode, $internalCatCode);

        return response()->json([
            'erp_item_code' => $code,
        ]);
    }

    /**
     * Create a new catalogue product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct($request->validated());

        return response()->json([
            'message' => 'Product catalogue item created successfully.',
            'product' => $product,
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $updated = $this->productService->updateProduct($product, $request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $updated,
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->productService->deleteProduct($product);

        return response()->json([
            'message' => 'Product removed from catalogue.',
        ]);
    }

    /**
     * Uniform Naming Validator and Assistant for Procurement Unit.
     */
    public function validateNaming(Request $request): JsonResponse
    {
        $description = (string) $request->input('description', '');
        $result = $this->namingService->validate($description);

        return response()->json($result);
    }

    /**
     * Export products to CSV matching the client's template.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->exportImportService->exportCsv($request->all());
    }

    /**
     * Import products from CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $result = $this->exportImportService->importCsv($request->file('file'));

        return response()->json([
            'message' => "Import complete. {$result['imported']} added, {$result['updated']} updated.",
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ]);
    }
}

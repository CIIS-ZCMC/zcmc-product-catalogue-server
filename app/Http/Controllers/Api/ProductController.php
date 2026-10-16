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
        $product = Product::with(['categoryRelation', 'internalCategory', 'priceHistories'])->findOrFail($id);

        return response()->json([
            'product' => $product,
            'category_path' => $product->full_category_path,
            'category_hierarchy' => $product->category_hierarchy,
            'price_history' => $product->priceHistories,
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
        $user = $request->user();

        // End users (non-admin) must verify their password to edit an existing product
        if (!$user || !$user->is_admin) {
            $password = $request->input('confirm_password') ?? $request->input('password');
            if (empty($password)) {
                return response()->json([
                    'message' => 'Password confirmation is required to update an existing catalogue item.',
                    'errors' => [
                        'confirm_password' => ['Password confirmation is required for end users.'],
                    ],
                ], 422);
            }

            if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid password confirmation. Please enter your correct account password.',
                    'errors' => [
                        'confirm_password' => ['The password you entered is incorrect.'],
                    ],
                ], 422);
            }
        }

        $product = Product::findOrFail($id);
        $data = $request->validated();
        unset($data['confirm_password'], $data['password']);

        $updated = $this->productService->updateProduct($product, $data);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $updated,
        ]);
    }

    /**
     * Delete a product (Administrator only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized action. Only administrators can delete catalogue products.',
            ], 403);
        }

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

        \App\Models\ActivityLog::record(
            'IMPORTED',
            "CSV bulk import: {$result['imported']} products added, {$result['updated']} products updated",
            'Product',
            null,
            'Bulk CSV Import',
            null,
            ['imported_count' => $result['imported'], 'updated_count' => $result['updated'], 'filename' => $request->file('file')->getClientOriginalName()]
        );

        return response()->json([
            'message' => "Import complete. {$result['imported']} added, {$result['updated']} updated.",
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ]);
    }

    /**
     * Upload Technical Specifications PDF file for a product.
     */
    public function uploadSpecsPdf(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('tech_specs', 'public');

        \App\Models\ActivityLog::record(
            'PDF_UPLOADED',
            "Uploaded technical specification PDF: {$originalName}",
            'Product',
            null,
            $originalName,
            null,
            ['original_filename' => $originalName, 'file_path' => $path]
        );

        return response()->json([
            'message' => 'Technical specifications PDF uploaded successfully.',
            'file_path' => $path,
            'file_url' => asset('storage/' . $path),
            'original_filename' => $originalName,
        ]);
    }

    /**
     * Retrieve unit price change history for a product.
     */
    public function priceHistory(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'product_id' => $product->id,
            'current_price' => (float) $product->unit_price,
            'history' => $product->priceHistories,
        ]);
    }
}

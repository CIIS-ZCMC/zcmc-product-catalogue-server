<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InternalCategoryController;
use App\Http\Controllers\Api\ItemUnitController;
use App\Http\Controllers\Api\AuthController;

// Public Authentication Endpoints
// Both /api/register and /api/auth/register work seamlessly
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected Endpoints - Accessible by Authenticated Users Only
Route::middleware('auth:sanctum')->group(function () {
    // Current User Profile & Logout
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Standard Item Units (ERP UOM)
    Route::get('/item-units', [ItemUnitController::class, 'index']);

    // Dashboard endpoints
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Internal Categories endpoints (Institutional Categories)
    Route::get('/internal-categories', [InternalCategoryController::class, 'index']);

    // Categories endpoints
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/search', [CategoryController::class, 'search']);
    Route::get('/categories/roots', [CategoryController::class, 'roots']);
    Route::get('/categories/commodities', [CategoryController::class, 'commodities']);
    Route::get('/categories/{key}/children', [CategoryController::class, 'children']);
    Route::get('/categories/{key}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{key}', [CategoryController::class, 'update']);
    Route::delete('/categories/{key}', [CategoryController::class, 'destroy']);

    // Products endpoints
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/next-erp-code', [ProductController::class, 'nextErpCode']);
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::post('/products/validate-naming', [ProductController::class, 'validateNaming']);
    Route::post('/products/import', [ProductController::class, 'import']);
    Route::post('/products/upload-specs-pdf', [ProductController::class, 'uploadSpecsPdf']);
    Route::get('/products/{id}/price-history', [ProductController::class, 'priceHistory']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});



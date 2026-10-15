<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalCategoryController extends Controller
{
    /**
     * List all institutional internal categories.
     */
    public function index(): JsonResponse
    {
        $categories = InternalCategory::orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }
}

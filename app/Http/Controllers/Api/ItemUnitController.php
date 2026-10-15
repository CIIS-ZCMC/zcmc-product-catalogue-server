<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemUnit;
use Illuminate\Http\JsonResponse;

class ItemUnitController extends Controller
{
    /**
     * Retrieve list of all available standard item units (UOM).
     */
    public function index(): JsonResponse
    {
        $units = ItemUnit::orderBy('name', 'asc')->get();

        return response()->json([
            'data' => $units,
        ]);
    }
}

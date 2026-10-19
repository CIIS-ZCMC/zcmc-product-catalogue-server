<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Retrieve paginated catalogue activity logs with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,first_name,last_name,email,is_admin')
            ->latest('id');

        // Filter by action (e.g. CREATED, UPDATED, DELETED, IMPORTED, PDF_UPLOADED)
        if ($request->filled('action')) {
            $query->where('action', strtoupper($request->query('action')));
        }

        // Search by item description, ERP code, user name, or email
        if ($request->filled('search')) {
            $s = trim($request->query('search'));
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                    ->orWhere('entity_identifier', 'like', "%{$s}%")
                    ->orWhere('user_name', 'like', "%{$s}%")
                    ->orWhere('user_email', 'like', "%{$s}%");
            });
        }

        // Filter by entity_type (Product, Category, etc.)
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        // Filter by specific product/entity ID
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Retrieve activity logs for a specific product.
     */
    public function productLogs(int $productId): JsonResponse
    {
        $logs = ActivityLog::with('user:id,first_name,last_name,email,is_admin')
            ->where('entity_type', 'Product')
            ->where('entity_id', $productId)
            ->latest('id')
            ->get();

        return response()->json([
            'product_id' => $productId,
            'logs' => $logs,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return summary statistics for procurement dashboard.
     */
    public function stats()
    {
        $totalProducts = \App\Models\Product::count();
        $totalCategories = \App\Models\Category::count();
        $totalSegments = \App\Models\Category::roots()->count();

        // Dynamically resolve category group distribution from product hierarchy
        $productsWithCategories = \App\Models\Product::with('categoryRelation')->get();
        $groupCounts = [];
        foreach ($productsWithCategories as $p) {
            $grp = $p->category_group ?: 'Uncategorized';
            $groupCounts[$grp] = ($groupCounts[$grp] ?? 0) + 1;
        }
        arsort($groupCounts);
        $groupDistribution = [];
        foreach (array_slice($groupCounts, 0, 8, true) as $groupName => $count) {
            $groupDistribution[] = [
                'category_group' => $groupName,
                'count' => $count,
            ];
        }

        $uomDistribution = \Illuminate\Support\Facades\DB::table('products')
            ->select('uom', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->whereNotNull('uom')
            ->groupBy('uom')
            ->orderBy('count', 'desc')
            ->limit(8)
            ->get();

        $recentProducts = \App\Models\Product::with('categoryRelation')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        // Check uniform naming standard: comma count >= 1 and UPPERCASE
        $compliantCount = \App\Models\Product::whereRaw('BINARY item_description = UPPER(item_description)')
            ->where('item_description', 'like', '%,%')
            ->count();

        $complianceRate = $totalProducts > 0 ? round(($compliantCount / $totalProducts) * 100, 1) : 100;

        return response()->json([
            'total_products' => $totalProducts,
            'total_categories' => $totalCategories,
            'total_segments' => $totalSegments,
            'compliance_rate' => $complianceRate,
            'group_distribution' => $groupDistribution,
            'uom_distribution' => $uomDistribution,
            'recent_products' => $recentProducts,
        ]);
    }
}

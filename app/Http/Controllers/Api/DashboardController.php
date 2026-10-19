<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return summary statistics for procurement dashboard.
     */
    public function stats()
    {
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $totalSegments = Category::roots()->count();

        // Dynamically resolve category group distribution from product hierarchy
        $productsWithCategories = Product::with('categoryRelation')->get();
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

        $uomDistribution = DB::table('products')
            ->select('uom', DB::raw('count(*) as count'))
            ->whereNotNull('uom')
            ->groupBy('uom')
            ->orderBy('count', 'desc')
            ->limit(8)
            ->get();

        $recentProducts = Product::with('categoryRelation')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        // Check uniform naming standard: comma count >= 1 and UPPERCASE
        $compliantCount = Product::whereRaw('BINARY item_description = UPPER(item_description)')
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

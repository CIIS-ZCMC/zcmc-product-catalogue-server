<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return summary statistics for procurement dashboard.
     */
    public function stats(): JsonResponse
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

        // Units of measure frequency stats (commented out)
        // $uomDistribution = DB::table('products')
        //     ->select('uom', DB::raw('count(*) as count'))
        //     ->whereNotNull('uom')
        //     ->groupBy('uom')
        //     ->orderBy('count', 'desc')
        //     ->limit(8)
        //     ->get();

        $recentProducts = Product::with(['categoryRelation', 'internalCategory'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        // Check uniform naming standard: comma count >= 1 and UPPERCASE
        $isMysql = DB::connection()->getDriverName() === 'mysql';
        $compliantQuery = Product::where('item_description', 'like', '%,%');
        if ($isMysql) {
            $compliantQuery->whereRaw('BINARY item_description = UPPER(item_description)');
        } else {
            $compliantQuery->whereRaw('item_description = UPPER(item_description)');
        }
        $compliantCount = $compliantQuery->count();

        $complianceRate = $totalProducts > 0 ? round(($compliantCount / $totalProducts) * 100, 1) : 100;

        // Internal Categories Statistics
        $totalInternalCategories = InternalCategory::count();
        $categorizedCount = Product::whereNotNull('internal_category_id')->count();
        $uncategorizedCount = $totalProducts - $categorizedCount;
        $internalCategoryCoverage = $totalProducts > 0
            ? round(($categorizedCount / $totalProducts) * 100, 1)
            : 100.0;

        $internalCategories = InternalCategory::withCount('products')
            ->orderBy('products_count', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $activeInternalCategoriesCount = $internalCategories->filter(fn ($cat) => $cat->products_count > 0)->count();

        $internalCategoryDistribution = $internalCategories->map(function ($cat) use ($totalProducts) {
            $percentage = $totalProducts > 0 ? round(($cat->products_count / $totalProducts) * 100, 1) : 0;
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'code' => $cat->code,
                'count' => (int) $cat->products_count,
                'percentage' => $percentage,
            ];
        })->values();

        return response()->json([
            'total_products' => $totalProducts,
            'total_categories' => $totalCategories,
            'total_segments' => $totalSegments,
            'compliance_rate' => $complianceRate,
            'total_internal_categories' => $totalInternalCategories,
            'active_internal_categories_count' => $activeInternalCategoriesCount,
            'internal_categorized_count' => $categorizedCount,
            'internal_uncategorized_count' => $uncategorizedCount,
            'internal_category_coverage' => $internalCategoryCoverage,
            'internal_category_distribution' => $internalCategoryDistribution,
            'group_distribution' => $groupDistribution,
            // 'uom_distribution' => $uomDistribution,
            'recent_products' => $recentProducts,
        ]);
    }
}

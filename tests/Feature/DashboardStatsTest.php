<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }

    public function test_dashboard_stats_includes_internal_category_statistics(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($user);

        // Seed two internal categories
        $office = InternalCategory::create(['code' => 'OF-SUP', 'name' => 'Office Supply']);
        $medical = InternalCategory::create(['code' => 'ME-SUP', 'name' => 'Medical Supply']);
        $empty = InternalCategory::create(['code' => 'DR-MED', 'name' => 'Drugs and Medicines']);

        // Seed a category
        $cat = Category::create([
            'key' => 101092,
            'code' => '14111507',
            'title' => 'Paper products',
            'level' => 3,
        ]);

        // Create 2 products in office, 1 in medical, 1 without internal category
        Product::create([
            'category_key' => $cat->key,
            'internal_category_id' => $office->id,
            'bizbox_item_code' => 'BB-1',
            'item_description' => 'PAPER, BOND, A4',
            'uom' => 'Ream',
        ]);
        Product::create([
            'category_key' => $cat->key,
            'internal_category_id' => $office->id,
            'bizbox_item_code' => 'BB-2',
            'item_description' => 'PAPER, BOND, LEGAL',
            'uom' => 'Ream',
        ]);
        Product::create([
            'category_key' => $cat->key,
            'internal_category_id' => $medical->id,
            'bizbox_item_code' => 'BB-3',
            'item_description' => 'GLOVES, EXAMINATION, LATEX, MEDIUM',
            'uom' => 'Box',
        ]);
        Product::create([
            'category_key' => $cat->key,
            'internal_category_id' => null,
            'bizbox_item_code' => 'BB-4',
            'item_description' => 'UNCATEGORIZED ITEM, GENERIC, SAMPLE',
            'uom' => 'Piece',
        ]);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('total_products', 4)
            ->assertJsonPath('total_internal_categories', 3)
            ->assertJsonPath('active_internal_categories_count', 2)
            ->assertJsonPath('internal_categorized_count', 3)
            ->assertJsonPath('internal_uncategorized_count', 1);

        $this->assertEquals(75.0, (float) $response->json('internal_category_coverage'));

        $distribution = collect($response->json('internal_category_distribution'));
        $this->assertCount(3, $distribution);

        // Office Supply should be top with 2 items (50%)
        $officeRow = $distribution->firstWhere('code', 'OF-SUP');
        $this->assertNotNull($officeRow);
        $this->assertSame(2, $officeRow['count']);
        $this->assertEquals(50.0, $officeRow['percentage']);

        // Medical Supply should have 1 item (25%)
        $medicalRow = $distribution->firstWhere('code', 'ME-SUP');
        $this->assertNotNull($medicalRow);
        $this->assertSame(1, $medicalRow['count']);
        $this->assertEquals(25.0, $medicalRow['percentage']);

        // Drugs and Medicines should have 0 items (0%)
        $emptyRow = $distribution->firstWhere('code', 'DR-MED');
        $this->assertNotNull($emptyRow);
        $this->assertSame(0, $emptyRow['count']);
        $this->assertEquals(0, $emptyRow['percentage']);
    }

    public function test_internal_categories_endpoint_includes_products_count(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $office = InternalCategory::create(['code' => 'OF-SUP', 'name' => 'Office Supply']);
        $cat = Category::create([
            'key' => 101092,
            'code' => '14111507',
            'title' => 'Paper products',
            'level' => 3,
        ]);

        Product::create([
            'category_key' => $cat->key,
            'internal_category_id' => $office->id,
            'bizbox_item_code' => 'BB-1',
            'item_description' => 'PAPER, BOND, A4',
            'uom' => 'Ream',
        ]);

        $response = $this->getJson('/api/internal-categories');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'code', 'products_count']]]);

        $this->assertSame(1, $response->json('data.0.products_count'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductPriceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private InternalCategory $office;
    private Category $category;
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = InternalCategory::create(['name' => 'Office Supplies', 'code' => 'OF-SUP']);
        $this->category = Category::create(['key' => 101, 'code' => '44120000', 'title' => 'Office Supplies']);
        $this->productService = app(ProductService::class);
    }

    public function test_price_change_creates_price_history_and_activity_log(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zcmc.gov.ph',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $product = Product::create([
            'erp_item_code' => '44120000-OF-SUP-0001',
            'item_description' => 'BALLPEN, RETRACTABLE, BLUE',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'uom' => 'PIECE',
            'unit_price' => 25.00,
        ]);

        $this->assertSame(0, ActivityLog::where('action', 'PRICE_UPDATED')->count());

        $this->productService->updateProduct($product, [
            'unit_price' => 30.00,
            'price_change_reason' => 'Annual supplier price adjustment',
        ]);

        // Assert ProductPriceHistory is logged
        $history = ProductPriceHistory::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertEquals(25.00, $history->old_price);
        $this->assertEquals(30.00, $history->new_price);
        $this->assertEquals(5.00, $history->price_change);
        $this->assertEquals(20.00, $history->percentage_change);
        $this->assertEquals('Annual supplier price adjustment', $history->reason);

        // Assert ActivityLog is logged
        $log = ActivityLog::where('action', 'PRICE_UPDATED')
            ->where('entity_id', $product->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Product', $log->entity_type);
        $this->assertSame($product->erp_item_code, $log->entity_identifier);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('Price updated for BALLPEN, RETRACTABLE, BLUE', $log->description);
        $this->assertStringContainsString('PHP 25.00 to PHP 30.00', $log->description);
        $this->assertStringContainsString('Annual supplier price adjustment', $log->description);

        $this->assertEquals(25.00, $log->old_values['unit_price']);
        $this->assertEquals(30.00, $log->new_values['unit_price']);
        $this->assertEquals(5.00, $log->new_values['price_change']);
        $this->assertEquals(20.00, $log->new_values['percentage_change']);
        $this->assertSame('Annual supplier price adjustment', $log->new_values['reason']);
    }

    public function test_no_price_activity_log_when_price_is_unchanged(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zcmc.gov.ph',
            'is_admin' => true,
        ]);
        Sanctum::actingAs($admin);

        $product = Product::create([
            'erp_item_code' => '44120000-OF-SUP-0002',
            'item_description' => 'CORRECTION TAPE, 5MM X 6M',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'uom' => 'PIECE',
            'unit_price' => 50.00,
        ]);

        $this->productService->updateProduct($product, [
            'unit_price' => 50.00, // unchanged
            'uom' => 'BOX',
        ]);

        $this->assertSame(0, ActivityLog::where('action', 'PRICE_UPDATED')->count());
        $this->assertSame(1, ActivityLog::where('action', 'UPDATED')->count());
    }
}

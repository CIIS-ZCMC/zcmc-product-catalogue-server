<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductStockCardNumberCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InternalCategory $office;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@zcmc.gov.ph',
        ]);
        Sanctum::actingAs($this->admin);

        $this->office = InternalCategory::create([
            'name' => 'Office Supply',
            'code' => 'OF-SUP',
        ]);

        $this->category = Category::create([
            'key' => 14111507,
            'code' => '14111507',
            'title' => 'Paper products',
            'level' => 3,
        ]);
    }

    public function test_can_create_product_with_stock_card_number(): void
    {
        $payload = [
            'noun' => 'PAPER',
            'type' => 'BOND',
            'material_composition' => 'WOODPULP',
            'key_attributes' => '70GSM',
            'size' => 'A4',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'bizbox_item_code' => 'BB-OFC-PPA4',
            'philgeps_code' => '14111507-PP-M01',
            'stock_card_number' => 'SC-2024-001',
            'uom' => 'Ream',
            'unit_price' => 220.50,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'bizbox_item_code' => 'BB-OFC-PPA4',
            'stock_card_number' => 'SC-2024-001',
        ]);

        $productId = $response->json('product.id');

        // Test show endpoint returns stock_card_number
        $showResponse = $this->getJson("/api/products/{$productId}");
        $showResponse->assertOk()
            ->assertJsonPath('product.stock_card_number', 'SC-2024-001');

        // Verify Activity Log includes stock_card_number
        $log = ActivityLog::where('entity_id', $productId)->first();
        $this->assertNotNull($log);
        $this->assertSame('SC-2024-001', $log->new_values['stock_card_number'] ?? null);
    }

    public function test_stock_card_number_is_optional_on_create(): void
    {
        $payload = [
            'noun' => 'FOLDER',
            'type' => 'EXPANDING',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'bizbox_item_code' => 'BB-OFC-FLD1',
            'uom' => 'Piece',
            'unit_price' => 15.00,
        ];

        $response = $this->postJson('/api/products', $payload);
        $response->assertCreated();

        $this->assertDatabaseHas('products', [
            'bizbox_item_code' => 'BB-OFC-FLD1',
            'stock_card_number' => null,
        ]);
    }

    public function test_can_update_product_stock_card_number(): void
    {
        $product = Product::create([
            'noun' => 'ENVELOPE',
            'item_description' => 'ENVELOPE, MAILING, WHITE',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'bizbox_item_code' => 'BB-OFC-ENV1',
            'stock_card_number' => 'SC-OLD-999',
            'uom' => 'Box',
            'unit_price' => 50.00,
        ]);

        $response = $this->putJson("/api/products/{$product->id}", [
            'noun' => 'ENVELOPE',
            'bizbox_item_code' => 'BB-OFC-ENV1',
            'uom' => 'Box',
            'stock_card_number' => 'SC-NEW-100',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_card_number' => 'SC-NEW-100',
        ]);

        // Verify ActivityLog tracked the old and new stock_card_number
        $log = ActivityLog::where('entity_id', $product->id)
            ->where('action', 'UPDATED')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('SC-OLD-999', $log->old_values['stock_card_number'] ?? null);
        $this->assertSame('SC-NEW-100', $log->new_values['stock_card_number'] ?? null);
    }

    public function test_can_search_product_by_stock_card_number(): void
    {
        Product::create([
            'noun' => 'TAPE',
            'item_description' => 'TAPE, PACKAGING, 2 INCH',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'bizbox_item_code' => 'BB-OFC-TPE2',
            'stock_card_number' => 'SC-TAPE-SPECIFIC-777',
            'uom' => 'Roll',
            'unit_price' => 45.00,
        ]);

        $searchResponse = $this->getJson('/api/products?search=SPECIFIC-777');
        $searchResponse->assertOk();

        $items = $searchResponse->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('SC-TAPE-SPECIFIC-777', $items[0]['stock_card_number']);
    }

    public function test_stock_card_number_max_length_validation(): void
    {
        $payload = [
            'noun' => 'PAPER',
            'category_key' => $this->category->key,
            'internal_category_id' => $this->office->id,
            'bizbox_item_code' => 'BB-OFC-MAX1',
            'stock_card_number' => str_repeat('A', 101),
            'uom' => 'Ream',
        ];

        $response = $this->postJson('/api/products', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock_card_number']);
    }
}

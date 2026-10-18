<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InternalCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password@1234';

    private InternalCategory $office;

    private InternalCategory $medical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = InternalCategory::create(['name' => 'Office Supplies', 'code' => 'OF-SUP']);
        $this->medical = InternalCategory::create(['name' => 'Medical', 'code' => 'MED']);
        Category::create(['key' => 1, 'code' => '10101', 'title' => 'Test']);
    }

    private function admin(array $attrs = []): User
    {
        return User::factory()->create(['is_admin' => true] + $attrs);
    }

    private function encoder(?array $categories = null, array $attrs = []): User
    {
        $user = User::factory()->create(['is_admin' => false] + $attrs);
        $user->internalCategories()->sync(array_map(fn ($c) => $c->id, $categories ?? [$this->office]));

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'Maria@ZCMC.gov.ph',
            'password' => 'Secret#123',
            'password_confirmation' => 'Secret#123',
            'is_admin' => false,
            'is_active' => true,
            'internal_category_ids' => [$this->office->id],
        ], $overrides);
    }

    private function productPayload(InternalCategory $category, array $overrides = []): array
    {
        return array_merge([
            'noun' => 'Pen',
            'category_key' => 1,
            'internal_category_id' => $category->id,
            'bizbox_item_code' => 'BB-1',
            'uom' => 'piece',
            'unit_price' => 10,
        ], $overrides);
    }

    // --- access control -------------------------------------------------------------------

    public function test_user_endpoints_require_authentication_and_admin(): void
    {
        $this->getJson('/api/users')->assertStatus(401);

        Sanctum::actingAs($this->encoder());
        $this->getJson('/api/users')->assertStatus(403);
        $this->postJson('/api/users', $this->payload())->assertStatus(403);
        $this->putJson('/api/users/1', $this->payload())->assertStatus(403);
        $this->deleteJson('/api/users/1')->assertStatus(403);
    }

    public function test_public_registration_is_removed(): void
    {
        $this->postJson('/api/register', $this->payload())->assertStatus(404);
        $this->postJson('/api/auth/register', $this->payload())->assertStatus(404);
    }

    // --- list -----------------------------------------------------------------------------

    public function test_index_is_paginated_and_filterable(): void
    {
        Sanctum::actingAs($this->admin(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@zcmc.gov.ph']));
        $this->encoder([$this->office], ['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@zcmc.gov.ph']);
        $this->encoder([$this->medical], ['first_name' => 'Jose', 'last_name' => 'Cruz', 'is_active' => false]);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total', 'from', 'to'])
            ->assertJsonPath('total', 3);

        $this->getJson('/api/users?search=maria santos')->assertJsonPath('total', 1);
        $this->getJson('/api/users?search=cruz')->assertJsonPath('total', 1);
        $this->getJson('/api/users?role=admin')->assertJsonPath('total', 1);
        $this->getJson('/api/users?role=encoder')->assertJsonPath('total', 2);
        $this->getJson('/api/users?status=inactive')->assertJsonPath('total', 1);
        $this->getJson('/api/users?status=active')->assertJsonPath('total', 2);
        $this->getJson('/api/users?internal_category_id=' . $this->medical->id)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.last_name', 'Cruz');
    }

    // --- create ---------------------------------------------------------------------------

    public function test_admin_creates_encoder(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/users', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.email', 'maria@zcmc.gov.ph')
            ->assertJsonPath('data.name', 'Maria Santos')
            ->assertJsonPath('data.is_admin', false)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.internal_category_ids', [$this->office->id])
            ->assertJsonPath('data.internal_categories.0.code', 'OF-SUP');

        $user = User::where('email', 'maria@zcmc.gov.ph')->firstOrFail();
        $this->assertNotSame('Secret#123', $user->password);
        $this->assertSame(1, ActivityLog::where('entity_type', 'User')->where('action', 'CREATED')->count());
    }

    public function test_create_validation(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/users', $this->payload([
            'first_name' => '', 'last_name' => '', 'password' => 'short1!', 'password_confirmation' => 'nope',
            'internal_category_ids' => [],
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'first_name', 'last_name', 'password', 'internal_category_ids',
        ]);

        $this->postJson('/api/users', $this->payload(['password' => 'NoNumber!!', 'password_confirmation' => 'NoNumber!!']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/users', $this->payload(['password' => 'NoSymbol123', 'password_confirmation' => 'NoSymbol123']))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/users', $this->payload(['internal_category_ids' => [9999]]))
            ->assertStatus(422)->assertJsonValidationErrors(['internal_category_ids.0']);
    }

    public function test_email_is_unique_case_insensitively_and_admin_needs_no_categories(): void
    {
        Sanctum::actingAs($this->admin());
        $this->encoder(null, ['email' => 'maria@zcmc.gov.ph']);

        $this->postJson('/api/users', $this->payload())->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/api/users', $this->payload(['email' => 'boss@zcmc.gov.ph', 'is_admin' => true, 'internal_category_ids' => []]))
            ->assertCreated()
            ->assertJsonPath('data.is_admin', true);
    }

    // --- update ---------------------------------------------------------------------------

    public function test_update_keeps_password_when_omitted_and_changes_it_when_given(): void
    {
        Sanctum::actingAs($this->admin());
        $target = $this->encoder();
        $oldHash = $target->password;

        $this->putJson("/api/users/{$target->id}", $this->payload([
            'email' => $target->email, 'password' => '', 'password_confirmation' => '',
            'internal_category_ids' => [$this->medical->id],
        ]))->assertOk()->assertJsonPath('data.internal_category_ids', [$this->medical->id]);
        $this->assertSame($oldHash, $target->fresh()->password);

        $this->putJson("/api/users/{$target->id}", $this->payload(['email' => $target->email]))->assertOk();
        $this->assertNotSame($oldHash, $target->fresh()->password);
    }

    public function test_deactivating_revokes_tokens_and_blocks_login(): void
    {
        $this->admin();
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $target = $this->encoder();
        $target->createToken('t');

        $this->putJson("/api/users/{$target->id}", $this->payload([
            'email' => $target->email, 'password' => null, 'password_confirmation' => null, 'is_active' => false,
        ]))->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $target->tokens()->count());

        $this->postJson('/api/auth/login', ['email' => $target->email, 'password' => self::PASSWORD])->assertStatus(403);
        $this->postJson('/api/auth/login', ['email' => $target->email, 'password' => 'wrong'])->assertStatus(422);
    }

    public function test_admin_cannot_demote_or_deactivate_self_or_remove_last_admin(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $base = ['password' => null, 'password_confirmation' => null, 'email' => $admin->email];

        $this->putJson("/api/users/{$admin->id}", $this->payload($base + ['is_admin' => false]))->assertStatus(422);
        $this->putJson("/api/users/{$admin->id}", $this->payload($base + ['is_admin' => true, 'is_active' => false]))->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_admin_while_one_remains(): void
    {
        $caller = $this->admin();
        $target = $this->admin();
        Sanctum::actingAs($caller);

        $this->deleteJson("/api/users/{$target->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $caller->id]);
    }

    // --- delete ---------------------------------------------------------------------------

    public function test_delete_keeps_activity_log_rows(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $target = $this->encoder();
        $log = ActivityLog::create([
            'user_id' => $target->id, 'user_name' => 'X', 'action' => 'CREATED', 'description' => 'd',
        ]);

        $this->deleteJson("/api/users/{$target->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertNull($log->fresh()->user_id);
        $this->assertDatabaseMissing('internal_category_user', ['user_id' => $target->id]);
    }

    // --- auth payloads --------------------------------------------------------------------

    public function test_login_and_me_return_user_shape(): void
    {
        $encoder = $this->encoder([$this->office, $this->medical]);

        $this->postJson('/api/auth/login', ['email' => $encoder->email, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.is_admin', false)
            ->assertJsonPath('user.is_active', true)
            ->assertJsonCount(2, 'user.internal_category_ids');

        Sanctum::actingAs($encoder);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonCount(2, 'user.internal_category_ids');
    }

    // --- product writes -------------------------------------------------------------------

    public function test_encoder_can_create_only_in_assigned_categories(): void
    {
        Sanctum::actingAs($this->encoder([$this->office]));

        $this->postJson('/api/products', $this->productPayload($this->medical))->assertStatus(403);
        $this->assertSame(0, Product::count());

        $this->postJson('/api/products', $this->productPayload($this->office))->assertCreated();
    }

    public function test_encoder_update_checks_old_and_new_category(): void
    {
        $encoder = $this->encoder([$this->office]);
        $mine = Product::create($this->productPayload($this->office, ['item_description' => 'A', 'erp_item_code' => 'E1']));
        $theirs = Product::create($this->productPayload($this->medical, ['item_description' => 'B', 'erp_item_code' => 'E2']));
        Sanctum::actingAs($encoder);

        $confirm = ['confirm_password' => self::PASSWORD];

        // Old category not assigned.
        $this->putJson("/api/products/{$theirs->id}", $this->productPayload($this->office, $confirm))->assertStatus(403);
        // New category not assigned.
        $this->putJson("/api/products/{$mine->id}", $this->productPayload($this->medical, $confirm))->assertStatus(403);
        // 403 takes precedence over a missing password confirmation.
        $this->putJson("/api/products/{$theirs->id}", $this->productPayload($this->medical))->assertStatus(403);
        // Allowed.
        $this->putJson("/api/products/{$mine->id}", $this->productPayload($this->office, $confirm + ['unit_price' => 20]))->assertOk();
        // Still must confirm password.
        $this->putJson("/api/products/{$mine->id}", $this->productPayload($this->office))->assertStatus(422);
    }

    public function test_admin_writes_any_category_and_only_admin_deletes(): void
    {
        $product = Product::create($this->productPayload($this->medical, ['item_description' => 'B', 'erp_item_code' => 'E2']));

        Sanctum::actingAs($this->encoder([$this->medical]));
        $this->deleteJson("/api/products/{$product->id}")->assertStatus(403);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/products', $this->productPayload($this->medical))->assertCreated();
        $this->putJson("/api/products/{$product->id}", $this->productPayload($this->office))->assertOk();
        $this->deleteJson("/api/products/{$product->id}")->assertOk();
    }

    public function test_encoder_import_is_rejected_for_unassigned_rows_and_rolls_back(): void
    {
        Product::create($this->productPayload($this->office, ['item_description' => 'A', 'erp_item_code' => 'MINE-1', 'unit_price' => 1]));
        Product::create($this->productPayload($this->medical, ['item_description' => 'B', 'erp_item_code' => 'THEIRS-1', 'unit_price' => 1]));

        $csv = "a,erp,bb,pg,un,Item Description,uom,price\n"
            . "1,MINE-1,,,,UPDATED MINE,piece,99\n"
            . "2,THEIRS-1,,,,UPDATED THEIRS,piece,99\n";
        $file = UploadedFile::fake()->createWithContent('p.csv', $csv);

        Sanctum::actingAs($this->encoder([$this->office]));
        $this->postJson('/api/products/import', ['file' => $file])->assertStatus(403);
        $this->assertEquals(1, Product::where('erp_item_code', 'MINE-1')->value('unit_price'));

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/products/import', ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
            ->assertOk()->assertJsonPath('updated', 2);
    }

    public function test_internal_categories_listing_stays_open_to_encoders(): void
    {
        Sanctum::actingAs($this->encoder());
        $this->getJson('/api/internal-categories')->assertOk()->assertJsonCount(2, 'data');
    }
}

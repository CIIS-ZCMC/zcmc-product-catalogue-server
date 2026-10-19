<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_activity_logs(): void
    {
        $response = $this->getJson('/api/activity-logs');
        $response->assertStatus(401);
    }

    public function test_admin_views_all_activity_logs(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zcmc.gov.ph',
            'is_admin' => true,
        ]);

        $encoder1 = User::factory()->create([
            'first_name' => 'Encoder',
            'last_name' => 'One',
            'email' => 'enc1@zcmc.gov.ph',
            'is_admin' => false,
        ]);

        $encoder2 = User::factory()->create([
            'first_name' => 'Encoder',
            'last_name' => 'Two',
            'email' => 'enc2@zcmc.gov.ph',
            'is_admin' => false,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => 'Admin User',
            'user_email' => $admin->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 1,
            'description' => 'Admin created product 1',
        ]);

        ActivityLog::create([
            'user_id' => $encoder1->id,
            'user_name' => 'Encoder One',
            'user_email' => $encoder1->email,
            'action' => 'UPDATED',
            'entity_type' => 'Product',
            'entity_id' => 2,
            'description' => 'Encoder 1 updated product 2',
        ]);

        ActivityLog::create([
            'user_id' => $encoder2->id,
            'user_name' => 'Encoder Two',
            'user_email' => $encoder2->email,
            'action' => 'DELETED',
            'entity_type' => 'Product',
            'entity_id' => 3,
            'description' => 'Encoder 2 deleted product 3',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/activity-logs');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_end_user_only_views_logs_involving_themselves(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zcmc.gov.ph',
            'is_admin' => true,
        ]);

        $encoder1 = User::factory()->create([
            'first_name' => 'Encoder',
            'last_name' => 'One',
            'email' => 'enc1@zcmc.gov.ph',
            'is_admin' => false,
        ]);

        $encoder2 = User::factory()->create([
            'first_name' => 'Encoder',
            'last_name' => 'Two',
            'email' => 'enc2@zcmc.gov.ph',
            'is_admin' => false,
        ]);

        // Log 1: Performed by Encoder 1
        ActivityLog::create([
            'user_id' => $encoder1->id,
            'user_name' => 'Encoder One',
            'user_email' => $encoder1->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 10,
            'description' => 'Encoder 1 created product',
        ]);

        // Log 2: Admin updated Encoder 1 account (involves Encoder 1)
        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => 'Admin User',
            'user_email' => $admin->email,
            'action' => 'UPDATED',
            'entity_type' => 'User',
            'entity_id' => $encoder1->id,
            'entity_identifier' => $encoder1->email,
            'description' => 'Admin assigned new categories to Encoder 1',
        ]);

        // Log 3: Performed by Encoder 2 (does NOT involve Encoder 1)
        ActivityLog::create([
            'user_id' => $encoder2->id,
            'user_name' => 'Encoder Two',
            'user_email' => $encoder2->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 20,
            'description' => 'Encoder 2 created product',
        ]);

        // Log 4: Admin created product (does NOT involve Encoder 1)
        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => 'Admin User',
            'user_email' => $admin->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 30,
            'description' => 'Admin created product',
        ]);

        Sanctum::actingAs($encoder1);

        $response = $this->getJson('/api/activity-logs');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $descriptions = array_column($data, 'description');
        $this->assertContains('Encoder 1 created product', $descriptions);
        $this->assertContains('Admin assigned new categories to Encoder 1', $descriptions);
        $this->assertNotContains('Encoder 2 created product', $descriptions);
        $this->assertNotContains('Admin created product', $descriptions);
    }

    public function test_admin_can_filter_by_user_id(): void
    {
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@zcmc.gov.ph',
            'is_admin' => true,
        ]);

        $encoder = User::factory()->create([
            'first_name' => 'Encoder',
            'last_name' => 'One',
            'email' => 'enc@zcmc.gov.ph',
            'is_admin' => false,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'user_name' => 'Admin User',
            'user_email' => $admin->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 1,
            'description' => 'Admin log',
        ]);

        ActivityLog::create([
            'user_id' => $encoder->id,
            'user_name' => 'Encoder One',
            'user_email' => $encoder->email,
            'action' => 'CREATED',
            'entity_type' => 'Product',
            'entity_id' => 2,
            'description' => 'Encoder log',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/activity-logs?user_id=' . $encoder->id);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Encoder log', $data[0]['description']);
    }
}

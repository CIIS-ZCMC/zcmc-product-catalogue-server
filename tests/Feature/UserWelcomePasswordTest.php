<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmailJob;
use App\Mail\WelcomeMail;
use App\Models\ActivityLog;
use App\Models\InternalCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserWelcomePasswordTest extends TestCase
{
    use RefreshDatabase;

    private InternalCategory $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = InternalCategory::create(['name' => 'Office Supplies', 'code' => 'OF-SUP']);
    }

    public function test_creating_user_sets_must_change_password_and_dispatches_email_with_initial_password(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $initialPassword = 'Initial@Password123';

        $response = $this->postJson('/api/users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@zcmc.gov.ph',
            'password' => $initialPassword,
            'password_confirmation' => $initialPassword,
            'is_admin' => false,
            'internal_category_ids' => [$this->office->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.must_change_password', true);

        $user = User::where('email', 'john.doe@zcmc.gov.ph')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($initialPassword, $user->password));

        // Assert welcome email job dispatched with plain initial password
        Queue::assertPushed(SendWelcomeEmailJob::class, function (SendWelcomeEmailJob $job) use ($user, $initialPassword) {
            return $job->user->id === $user->id && $job->initialPassword === $initialPassword;
        });
    }

    public function test_creating_user_without_password_autogenerates_initial_password(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'first_name' => 'Auto',
            'last_name' => 'User',
            'email' => 'auto.user@zcmc.gov.ph',
            'is_admin' => false,
            'internal_category_ids' => [$this->office->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.must_change_password', true);

        $user = User::where('email', 'auto.user@zcmc.gov.ph')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertNotEmpty($user->password);

        // Assert welcome email job dispatched with auto-generated initial password
        Queue::assertPushed(SendWelcomeEmailJob::class, function (SendWelcomeEmailJob $job) use ($user) {
            $initialPassword = $job->initialPassword;
            return $job->user->id === $user->id
                && strlen($initialPassword) >= 8
                && Hash::check($initialPassword, $user->password);
        });
    }

    public function test_welcome_mail_renders_initial_credentials_card(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@zcmc.gov.ph',
            'must_change_password' => true,
        ]);

        $initialPassword = 'Temp@Secret987';
        $mailable = new WelcomeMail($user, $initialPassword);

        $rendered = $mailable->render();

        $this->assertStringContainsString('Your Initial Login Credentials:', $rendered);
        $this->assertStringContainsString('jane.smith@zcmc.gov.ph', $rendered);
        $this->assertStringContainsString('Temp@Secret987', $rendered);
        $this->assertStringContainsString('First-Time Sign-In:', $rendered);
    }

    public function test_first_time_login_shows_must_change_password_flag(): void
    {
        $user = User::factory()->create([
            'email' => 'first.time@zcmc.gov.ph',
            'password' => 'Initial@Pass123',
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'first.time@zcmc.gov.ph',
            'password' => 'Initial@Pass123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.must_change_password', true);
    }

    public function test_first_time_password_change_does_not_require_initial_password(): void
    {
        $initialPassword = 'Initial@Pass123';
        $user = User::factory()->create([
            'email' => 'encoder.test@zcmc.gov.ph',
            'password' => $initialPassword,
            'must_change_password' => true,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        // 1. Setting new password identical to initial password is rejected
        $this->postJson('/api/auth/change-password', [
            'password' => $initialPassword,
            'password_confirmation' => $initialPassword,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);

        // 2. Setting weak new password is rejected
        $this->postJson('/api/auth/change-password', [
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);

        // 3. Setting valid new password without providing initial password succeeds
        $newPassword = 'MyOwnNew@Password2026';
        $res = $this->postJson('/api/auth/change-password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $res->assertOk();
        $res->assertJsonPath('user.must_change_password', false);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $fresh->password));

        // 4. Once must_change_password is false, subsequent changes require current_password
        $this->postJson('/api/auth/change-password', [
            'password' => 'Another@Password789',
            'password_confirmation' => 'Another@Password789',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['current_password']);

        // 5. Wrong current password fails
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'WrongCurrent@123',
            'password' => 'Another@Password789',
            'password_confirmation' => 'Another@Password789',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['current_password']);

        // 6. Correct current password succeeds
        $this->postJson('/api/auth/change-password', [
            'current_password' => $newPassword,
            'password' => 'Another@Password789',
            'password_confirmation' => 'Another@Password789',
        ])->assertOk();

        // Check activity log recorded
        $log = ActivityLog::where('action', 'PASSWORD_CHANGED')
            ->where('entity_id', $user->id)
            ->first();
        $this->assertNotNull($log);

        // User can now log in with the new password
        $this->postJson('/api/auth/login', [
            'email' => 'encoder.test@zcmc.gov.ph',
            'password' => 'Another@Password789',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
    }
}

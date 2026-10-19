<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_admin_and_office_supply_encoder_idempotently(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertTrue(User::where('email', 'admin@zcmc.com')->firstOrFail()->is_admin);

        $encoder = User::where('email', 'encoder.office@zcmc.com')->firstOrFail();
        $this->assertFalse($encoder->is_admin);
        $this->assertSame(['OF-SUP'], $encoder->internalCategories->pluck('code')->all());

        $this->postJson('/api/auth/login', ['email' => $encoder->email, 'password' => 'Encoder@123456'])
            ->assertOk();
    }
}

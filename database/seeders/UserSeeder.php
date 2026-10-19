<?php

namespace Database\Seeders;

use App\Models\InternalCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed one administrator and one encoder assigned to Office Supply only.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@zcmc.com'],
            [
                'first_name' => 'ZCMC',
                'last_name' => 'Admin',
                'password' => 'Admin@123456',
                'is_admin' => true,
                'is_active' => true,
            ]
        );

        $encoder = User::updateOrCreate(
            ['email' => 'encoder.office@zcmc.com'],
            [
                'first_name' => 'Office Supply',
                'last_name' => 'Encoder',
                'password' => 'Encoder@123456',
                'is_admin' => false,
                'is_active' => true,
            ]
        );

        $officeSupply = InternalCategory::firstOrCreate(
            ['code' => 'OF-SUP'],
            ['name' => 'Office Supply']
        );

        $encoder->internalCategories()->sync([$officeSupply->id]);
    }
}

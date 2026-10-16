<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'first_name' => 'ZCMC',
                'last_name' => 'Admin',
                'email' => 'admin@zcmc.gov.ph',
                'password' => Hash::make('Admin@123456'),
                'is_admin' => true,
            ],
            [
                'first_name' => 'Procurement',
                'last_name' => 'Officer',
                'email' => 'procurement@zcmc.gov.ph',
                'password' => Hash::make('Procurement@123456'),
                'is_admin' => false,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}

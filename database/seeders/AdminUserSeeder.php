<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin User
        User::updateOrCreate(
            ['email' => 'admin@inventory.com'],
            [
                'name'              => 'Admin User',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // Manager User
        User::updateOrCreate(
            ['email' => 'manager@inventory.com'],
            [
                'name'              => 'Manager User',
                'password'          => Hash::make('password'),
                'role'              => 'manager',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // Staff User
        User::updateOrCreate(
            ['email' => 'staff@inventory.com'],
            [
                'name'              => 'Staff User',
                'password'          => Hash::make('password'),
                'role'              => 'staff',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
    }
}

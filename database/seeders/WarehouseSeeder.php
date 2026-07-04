<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        Warehouse::updateOrCreate(
            ['code' => 'WH-001'],
            [
                'name'       => 'Main Warehouse',
                'address'    => '123 Main Street',
                'city'       => 'Dhaka',
                'country'    => 'Bangladesh',
                'phone'      => '+880 1234-567890',
                'manager_id' => $admin?->id,
                'is_default' => true,
                'is_active'  => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'WH-002'],
            [
                'name'       => 'Secondary Warehouse',
                'address'    => '456 Secondary Street',
                'city'       => 'Chittagong',
                'country'    => 'Bangladesh',
                'phone'      => '+880 1234-567891',
                'manager_id' => $admin?->id,
                'is_default' => false,
                'is_active'  => true,
            ]
        );
    }
}

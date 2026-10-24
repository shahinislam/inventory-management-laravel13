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
                'name' => 'Main Warehouse',
                'address' => '123 Main Street',
                'city' => 'Springfield',
                'country' => 'United States',
                'phone' => '+1 555-0110',
                'manager_id' => $admin?->id,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'WH-002'],
            [
                'name' => 'Secondary Warehouse',
                'address' => '456 Secondary Street',
                'city' => 'Shelbyville',
                'country' => 'United States',
                'phone' => '+1 555-0111',
                'manager_id' => $admin?->id,
                'is_default' => false,
                'is_active' => true,
            ]
        );
    }
}

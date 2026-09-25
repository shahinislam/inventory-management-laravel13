<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            AdminUserSeeder::class,
            SettingsSeeder::class,
            WarehouseSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            PartnerSeeder::class,
            PaymentAccountSeeder::class,
            SampleTransactionSeeder::class,
        ]);
    }
}

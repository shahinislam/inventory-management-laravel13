<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            'Northwind Traders',
            'Green Valley Foods',
            'Bright Home Supplies',
        ];

        foreach ($suppliers as $name) {
            Supplier::updateOrCreate(
                ['name' => $name],
                [
                    'city' => 'Springfield',
                    'country' => 'United States',
                    'payment_terms' => 'net_30',
                    'is_active' => true,
                ]
            );
        }
    }
}

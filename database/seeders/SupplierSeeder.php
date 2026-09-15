<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            'Aminur Islam',
            'Mahbuba Jannat',
            'Jesmin Akter',
        ];

        foreach ($suppliers as $name) {
            Supplier::updateOrCreate(
                ['name' => $name],
                [
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'payment_terms' => 'net_30',
                    'is_active' => true,
                ]
            );
        }
    }
}

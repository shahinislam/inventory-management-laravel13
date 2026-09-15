<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => "Muhammad Mus'ab", 'gender' => 'male'],
            ['name' => 'Zayan', 'gender' => 'male'],
            ['name' => 'Zareef', 'gender' => 'male'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['name' => $customer['name']],
                [
                    'gender' => $customer['gender'],
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'is_active' => true,
                ]
            );
        }
    }
}

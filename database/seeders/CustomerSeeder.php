<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Every customer is a member, identified by a unique phone number.
        $customers = [
            ['name' => 'Emma Wilson', 'gender' => 'female', 'phone' => '+1 555-0101'],
            ['name' => 'Liam Carter', 'gender' => 'male', 'phone' => '+1 555-0102'],
            ['name' => 'Sophia Patel', 'gender' => 'female', 'phone' => '+1 555-0103'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['phone' => $customer['phone']],
                [
                    'name' => $customer['name'],
                    'gender' => $customer['gender'],
                    'city' => 'Springfield',
                    'country' => 'United States',
                    'is_active' => true,
                ]
            );
        }
    }
}

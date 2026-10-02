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
            ['name' => "Muhammad Mus'ab", 'gender' => 'male', 'phone' => '01711000001'],
            ['name' => 'Zayan', 'gender' => 'male', 'phone' => '01811000002'],
            ['name' => 'Zareef', 'gender' => 'male', 'phone' => '01911000003'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['phone' => $customer['phone']],
                [
                    'name' => $customer['name'],
                    'gender' => $customer['gender'],
                    'city' => 'Dhaka',
                    'country' => 'Bangladesh',
                    'is_active' => true,
                ]
            );
        }
    }
}

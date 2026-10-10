<?php

namespace Database\Seeders;

use App\Models\PaymentAccount;
use Illuminate\Database\Seeder;

class PaymentAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'DBBL Current Account', 'type' => 'bank', 'bank_name' => 'Dutch-Bangla Bank', 'account_number' => '1011100045671', 'holder_name' => 'Swift Inventory Ltd.'],
            ['name' => 'City Bank Savings', 'type' => 'bank', 'bank_name' => 'The City Bank', 'account_number' => '2302456789001', 'holder_name' => 'Swift Inventory Ltd.'],
            ['name' => 'Company Visa', 'type' => 'card', 'bank_name' => 'BRAC Bank', 'account_number' => '4532', 'holder_name' => 'Osman Goni Shuvo'],
            ['name' => 'Amex Corporate', 'type' => 'card', 'bank_name' => 'The City Bank', 'account_number' => '1009', 'holder_name' => 'Abdur Rahman'],
            ['name' => 'Wallet Merchant', 'type' => 'mobile_wallet', 'bank_name' => 'Mobile Wallet', 'account_number' => '+1 555 010 0001'],
            ['name' => 'PayPal Business', 'type' => 'mobile_wallet', 'bank_name' => 'PayPal', 'account_number' => 'shop@example.com'],
        ];

        foreach ($accounts as $account) {
            PaymentAccount::updateOrCreate(
                ['name' => $account['name']],
                $account + ['is_active' => true]
            );
        }
    }
}

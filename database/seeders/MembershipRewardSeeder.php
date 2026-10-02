<?php

namespace Database\Seeders;

use App\Models\MembershipReward;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MembershipRewardSeeder extends Seeder
{
    public function run(): void
    {
        $gift = Product::where('sku', 'PRAN-MNG-250ML')->first() ?? Product::first();

        $rewards = [
            [
                'name' => 'Big basket discount',
                'basis' => 'single_invoice',
                'min_amount' => 2000,
                'max_amount' => 4999.99,
                'reward_type' => 'percent',
                'value' => 3,
            ],
            [
                'name' => 'Bulk buyer discount',
                'basis' => 'single_invoice',
                'min_amount' => 5000,
                'max_amount' => null,
                'reward_type' => 'fixed',
                'value' => 250,
            ],
            [
                'name' => 'Loyal member gift',
                'basis' => 'cumulative',
                'min_amount' => 10000,
                'max_amount' => null,
                'reward_type' => 'gift',
                'value' => 0,
                'gift_product_id' => $gift?->id,
                'gift_quantity' => 2,
            ],
            [
                'name' => 'Gold member thank-you',
                'basis' => 'cumulative',
                'min_amount' => 50000,
                'max_amount' => null,
                'reward_type' => 'percent',
                'value' => 10,
            ],
        ];

        foreach ($rewards as $reward) {
            // Skip the gift rule if there is no product to give.
            if ($reward['reward_type'] === 'gift' && ! $reward['gift_product_id']) {
                continue;
            }

            MembershipReward::updateOrCreate(['name' => $reward['name']], $reward + ['is_active' => true]);
        }
    }
}

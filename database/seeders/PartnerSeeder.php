<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        // Active partner shares must sum to 100 (see PartnerForm).
        $partners = [
            ['name' => 'Osman Goni Shuvo', 'share_percentage' => 50],
            ['name' => 'Abdur Rahman', 'share_percentage' => 50],
        ];

        foreach ($partners as $partner) {
            Partner::updateOrCreate(
                ['name' => $partner['name']],
                [
                    'share_percentage' => $partner['share_percentage'],
                    'is_active' => true,
                ]
            );
        }
    }
}

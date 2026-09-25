<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app.name',              'value' => 'Swift Inventory',  'group' => 'general',      'type' => 'text',    'is_public' => true],
            ['key' => 'app.timezone',           'value' => 'UTC',              'group' => 'general',      'type' => 'text',    'is_public' => true],
            ['key' => 'app.language',           'value' => 'en',               'group' => 'general',      'type' => 'text',    'is_public' => true],

            // Company
            ['key' => 'company.name',           'value' => 'My Company',       'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.logo',           'value' => null,               'group' => 'company',      'type' => 'image',   'is_public' => true],
            ['key' => 'company.email',          'value' => null,               'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.phone',          'value' => null,               'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.address',        'value' => null,               'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.tax_number',     'value' => null,               'group' => 'company',      'type' => 'text',    'is_public' => false],

            // Currency
            ['key' => 'currency.symbol',        'value' => '৳',                'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.code',          'value' => 'BDT',              'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.position',      'value' => 'before',           'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.decimals',      'value' => '2',                'group' => 'currency',     'type' => 'number',  'is_public' => true],

            // Tax
            ['key' => 'tax.rate',               'value' => '0',                'group' => 'tax',          'type' => 'number',  'is_public' => true],
            ['key' => 'tax.inclusive',          'value' => 'false',            'group' => 'tax',          'type' => 'boolean', 'is_public' => true],

            // Invoice
            ['key' => 'invoice.prefix',         'value' => 'INV',              'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'invoice.next_number',    'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],
            ['key' => 'invoice.footer',         'value' => 'Thank you!',       'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'invoice.terms',          'value' => null,               'group' => 'invoice',      'type' => 'text',    'is_public' => false],

            // Purchase
            ['key' => 'purchase.prefix',        'value' => 'PO',               'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'purchase.next_number',   'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],

            // Payment
            ['key' => 'payment.prefix',         'value' => 'PAY',              'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'payment.next_number',    'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],

            // Purchase payment
            ['key' => 'purchase_payment.prefix',      'value' => 'PP',   'group' => 'invoice', 'type' => 'text',   'is_public' => false],
            ['key' => 'purchase_payment.next_number', 'value' => '1',    'group' => 'invoice', 'type' => 'number', 'is_public' => false],

            // Notification
            ['key' => 'notification.low_stock', 'value' => 'true',             'group' => 'notification', 'type' => 'boolean', 'is_public' => false],
            ['key' => 'notification.expiry',    'value' => 'true',             'group' => 'notification', 'type' => 'boolean', 'is_public' => false],
            ['key' => 'notification.days',      'value' => '30',               'group' => 'notification', 'type' => 'number',  'is_public' => false],

            // POS
            ['key' => 'pos.receipt_footer',     'value' => 'Thank you!',       'group' => 'pos',          'type' => 'text',    'is_public' => false],
            ['key' => 'pos.print_receipt',      'value' => 'true',             'group' => 'pos',          'type' => 'boolean', 'is_public' => false],
            ['key' => 'pos.barcode_scanner',    'value' => 'true',             'group' => 'pos',          'type' => 'boolean', 'is_public' => false],

            // Theme — brand colours. Stored as hex and injected as CSS custom
            // properties at render time, so changing one repaints the whole UI.
            ['key' => 'theme.primary',          'value' => '#4f46e5',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
            ['key' => 'theme.secondary',        'value' => '#0d9488',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
            ['key' => 'theme.tertiary',         'value' => '#d97706',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}

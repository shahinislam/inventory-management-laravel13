<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Company
            ['key' => 'company.name',           'value' => 'My Company',       'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.logo',           'value' => null,               'group' => 'company',      'type' => 'image',   'is_public' => true],
            ['key' => 'company.email',          'value' => 'hello@example.com', 'group' => 'company',     'type' => 'text',    'is_public' => true],
            ['key' => 'company.phone',          'value' => '+1 555-0100',      'group' => 'company',      'type' => 'text',    'is_public' => true],
            ['key' => 'company.address',        'value' => '123 Main Street, Springfield', 'group' => 'company', 'type' => 'text', 'is_public' => true],
            ['key' => 'company.tax_number',     'value' => null,               'group' => 'company',      'type' => 'text',    'is_public' => false],

            // Currency
            ['key' => 'currency.symbol',        'value' => '$',                'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.code',          'value' => 'USD',              'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.position',      'value' => 'before',           'group' => 'currency',     'type' => 'text',    'is_public' => true],
            ['key' => 'currency.decimals',      'value' => '2',                'group' => 'currency',     'type' => 'number',  'is_public' => true],

            // Tax
            ['key' => 'tax.rate',               'value' => '0',                'group' => 'tax',          'type' => 'number',  'is_public' => true],
            // On: selling prices already contain tax, which is extracted rather than added on top.
            ['key' => 'tax.prices_include_tax', 'value' => 'false',            'group' => 'tax',          'type' => 'boolean', 'is_public' => true],

            // Invoice
            ['key' => 'invoice.prefix',         'value' => 'INV',              'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'invoice.next_number',    'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],
            ['key' => 'invoice.footer',         'value' => 'Thank you for your business!', 'group' => 'invoice', 'type' => 'text', 'is_public' => false],
            ['key' => 'invoice.terms',          'value' => 'Items can be returned or exchanged within 7 days with this invoice.', 'group' => 'invoice', 'type' => 'text', 'is_public' => false],

            // Purchase
            ['key' => 'purchase.prefix',        'value' => 'PO',               'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'purchase.next_number',   'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],

            // Payment
            ['key' => 'payment.prefix',         'value' => 'PAY',              'group' => 'invoice',      'type' => 'text',    'is_public' => false],
            ['key' => 'payment.next_number',    'value' => '1',                'group' => 'invoice',      'type' => 'number',  'is_public' => false],

            // Purchase payment
            ['key' => 'purchase_payment.prefix',      'value' => 'PP',   'group' => 'invoice', 'type' => 'text',   'is_public' => false],
            ['key' => 'purchase_payment.next_number', 'value' => '1',    'group' => 'invoice', 'type' => 'number', 'is_public' => false],

            // Other document numbers
            ['key' => 'sales_return.prefix',          'value' => 'SR',   'group' => 'invoice', 'type' => 'text',   'is_public' => false],
            ['key' => 'purchase_return.prefix',       'value' => 'PR',   'group' => 'invoice', 'type' => 'text',   'is_public' => false],
            ['key' => 'shift.prefix',                 'value' => 'SH',   'group' => 'invoice', 'type' => 'text',   'is_public' => false],
            ['key' => 'expense.prefix',               'value' => 'EXP',  'group' => 'invoice', 'type' => 'text',   'is_public' => false],

            // SMS — a generic HTTP gateway. Until enabled and configured, messages are only logged.
            ['key' => 'sms.enabled',            'value' => 'false',            'group' => 'sms',          'type' => 'boolean', 'is_public' => false],
            ['key' => 'sms.gateway_url',        'value' => null,               'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.http_method',        'value' => 'POST',             'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.api_key',            'value' => null,               'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.sender_id',          'value' => null,               'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.param_to',           'value' => 'number',           'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.param_message',      'value' => 'message',          'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.param_key',          'value' => 'api_key',          'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.param_sender',       'value' => 'senderid',         'group' => 'sms',          'type' => 'text',    'is_public' => false],
            ['key' => 'sms.send_receipt',       'value' => 'false',            'group' => 'sms',          'type' => 'boolean', 'is_public' => false],
            ['key' => 'sms.receipt_template',   'value' => 'Thank you {name}! Receipt {invoice}: {total}. Total spent: {spent}. - {shop}', 'group' => 'sms', 'type' => 'text', 'is_public' => false],
            ['key' => 'sms.reward_template',    'value' => 'Congrats {name}! You earned {reward} on receipt {invoice}. - {shop}', 'group' => 'sms', 'type' => 'text', 'is_public' => false],

            // Notification
            ['key' => 'notification.days',      'value' => '30',               'group' => 'notification', 'type' => 'number',  'is_public' => false],

            // POS
            ['key' => 'pos.wallet_label',       'value' => 'Mobile Wallet',    'group' => 'pos',          'type' => 'text',    'is_public' => true],

            // Theme — brand colours. Stored as hex and injected as CSS custom
            // properties at render time, so changing one repaints the whole UI.
            ['key' => 'theme.primary',          'value' => '#0f7a55',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
            ['key' => 'theme.secondary',        'value' => '#b45309',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
            ['key' => 'theme.sidebar',          'value' => 'light',            'group' => 'theme',        'type' => 'text',    'is_public' => true],
            ['key' => 'theme.tertiary',         'value' => '#1d6fa5',          'group' => 'theme',        'type' => 'text',    'is_public' => true],
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

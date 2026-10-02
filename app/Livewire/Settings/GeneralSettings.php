<?php

namespace App\Livewire\Settings;

use App\Models\Media;
use App\Models\Setting;
use App\Services\SmsService;
use App\Support\ThemeColors;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class GeneralSettings extends Component
{
    // Company
    public string $company_name = '';

    public ?int $company_logo_id = null;

    public string $company_email = '';

    public string $company_phone = '';

    public string $company_address = '';

    public string $company_tax_number = '';

    /** VAT Business Identification Number, printed on the Mushak 6.3 invoice. */
    public string $company_bin = '';

    public bool $mushak_enabled = false;

    // SMS gateway (generic HTTP). Until enabled + URL set, messages are only logged.
    public bool $sms_enabled = false;

    public string $sms_gateway_url = '';

    public string $sms_http_method = 'POST';

    public string $sms_api_key = '';

    public string $sms_sender_id = '';

    public string $sms_param_to = 'number';

    public string $sms_param_message = 'message';

    public string $sms_param_key = 'api_key';

    public string $sms_param_sender = 'senderid';

    public bool $sms_send_receipt = false;

    public string $sms_receipt_template = '';

    public string $sms_reward_template = '';

    public string $sms_test_phone = '';

    // Currency
    public string $currency_symbol = '';

    public string $currency_code = '';

    public string $currency_position = 'before';

    public string $currency_decimals = '2';

    // Tax
    public string $tax_rate = '0';

    public bool $tax_inclusive = false;

    // Invoice
    public string $invoice_prefix = '';

    public string $invoice_footer = '';

    public string $invoice_terms = '';

    // Notification
    public bool $notification_low_stock = true;

    public bool $notification_expiry = true;

    public string $notification_days = '30';

    // Theme — brand colours, stored as hex and expanded into full OKLCH ramps
    // by App\Support\ThemeColors.
    public string $theme_primary = '#4f46e5';

    public string $theme_secondary = '#0d9488';

    public string $theme_tertiary = '#d97706';

    /** Left menu colour: light, dark or brand (filled with the primary colour). */
    public string $theme_sidebar = 'light';

    public bool $showMediaPicker = false;

    protected $listeners = ['select-media' => 'selectLogo'];

    public function mount(): void
    {
        $this->company_name = Setting::get('company.name', '') ?? '';
        $this->company_logo_id = null;
        $this->company_email = Setting::get('company.email', '') ?? '';
        $this->company_phone = Setting::get('company.phone', '') ?? '';
        $this->company_address = Setting::get('company.address', '') ?? '';
        $this->company_tax_number = Setting::get('company.tax_number', '') ?? '';
        $this->company_bin = Setting::get('company.bin', '') ?? '';
        $this->mushak_enabled = Setting::bool('tax.mushak_enabled');

        $this->sms_enabled = Setting::bool('sms.enabled');
        foreach (['gateway_url', 'http_method', 'api_key', 'sender_id', 'param_to', 'param_message', 'param_key', 'param_sender', 'receipt_template', 'reward_template'] as $key) {
            $this->{'sms_'.$key} = (string) (Setting::get('sms.'.$key, $this->{'sms_'.$key}) ?? '');
        }
        $this->sms_send_receipt = Setting::bool('sms.send_receipt');

        $this->currency_symbol = Setting::get('currency.symbol', '$');
        $this->currency_code = Setting::get('currency.code', 'USD');
        $this->currency_position = Setting::get('currency.position', 'before');
        $this->currency_decimals = (string) Setting::get('currency.decimals', 2);

        $this->tax_rate = (string) Setting::get('tax.rate', 0);
        $this->tax_inclusive = Setting::get('tax.inclusive', false);

        $this->invoice_prefix = Setting::get('invoice.prefix', 'INV') ?? 'INV';
        $this->invoice_footer = Setting::get('invoice.footer', '') ?? '';
        $this->invoice_terms = Setting::get('invoice.terms', '') ?? '';

        $this->notification_low_stock = Setting::get('notification.low_stock', true);
        $this->notification_expiry = Setting::get('notification.expiry', true);
        $this->notification_days = (string) Setting::get('notification.days', 30);

        $this->theme_primary = ThemeColors::normalizeHex(Setting::get('theme.primary')) ?? '#4f46e5';
        $this->theme_secondary = ThemeColors::normalizeHex(Setting::get('theme.secondary')) ?? '#0d9488';
        $this->theme_tertiary = ThemeColors::normalizeHex(Setting::get('theme.tertiary')) ?? '#d97706';
        $this->theme_sidebar = in_array(Setting::get('theme.sidebar'), ['light', 'dark', 'brand'], true) ? Setting::get('theme.sidebar') : 'light';
    }

    /** Picking a sidebar style applies it straight away — no Save needed. */
    public function updatedThemeSidebar(): void
    {
        $this->validateOnly('theme_sidebar', ['theme_sidebar' => 'required|in:light,dark,brand']);

        Setting::set('theme.sidebar', $this->theme_sidebar);

        $this->redirect(route('settings.general'));
    }

    public function selectLogo(int $mediaId): void
    {
        $this->company_logo_id = $mediaId;
        $this->showMediaPicker = false;
        $media = Media::find($mediaId);
        Setting::set('company.logo', $media->file_url);
    }

    public function save(): void
    {
        $this->validate([
            'company_name' => 'required|string|max:200',
            'company_email' => 'nullable|email',
            'company_bin' => 'nullable|string|max:30',
            'sms_gateway_url' => $this->sms_enabled ? 'required|url' : 'nullable|url',
            'sms_http_method' => 'required|in:GET,POST',
            'invoice_prefix' => 'required|string|max:10',
            // Accept #rgb or #rrggbb; anything else would produce broken CSS.
            'theme_primary' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'theme_secondary' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'theme_tertiary' => ['required', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'theme_sidebar' => 'required|in:light,dark,brand',
        ], [], [
            'theme_primary' => 'primary colour',
            'theme_secondary' => 'secondary colour',
            'theme_tertiary' => 'tertiary colour',
        ]);

        Setting::set('company.name', $this->company_name);
        Setting::set('company.email', $this->company_email);
        Setting::set('company.phone', $this->company_phone);
        Setting::set('company.address', $this->company_address);
        Setting::set('company.tax_number', $this->company_tax_number);
        Setting::set('company.bin', $this->company_bin);
        Setting::set('tax.mushak_enabled', $this->mushak_enabled ? 'true' : 'false');

        Setting::set('sms.enabled', $this->sms_enabled ? 'true' : 'false');
        Setting::set('sms.send_receipt', $this->sms_send_receipt ? 'true' : 'false');
        foreach (['gateway_url', 'http_method', 'api_key', 'sender_id', 'param_to', 'param_message', 'param_key', 'param_sender', 'receipt_template', 'reward_template'] as $key) {
            Setting::set('sms.'.$key, $this->{'sms_'.$key});
        }

        Setting::set('currency.symbol', $this->currency_symbol);
        Setting::set('currency.code', $this->currency_code);
        Setting::set('currency.position', $this->currency_position);
        Setting::set('currency.decimals', $this->currency_decimals);

        Setting::set('tax.rate', $this->tax_rate);
        Setting::set('tax.inclusive', $this->tax_inclusive ? 'true' : 'false');

        Setting::set('invoice.prefix', $this->invoice_prefix);
        Setting::set('invoice.footer', $this->invoice_footer);
        Setting::set('invoice.terms', $this->invoice_terms);

        Setting::set('notification.low_stock', $this->notification_low_stock ? 'true' : 'false');
        Setting::set('notification.expiry', $this->notification_expiry ? 'true' : 'false');
        Setting::set('notification.days', $this->notification_days);

        // Normalize so a 3-digit shorthand is stored expanded and lowercased.
        Setting::set('theme.primary', ThemeColors::normalizeHex($this->theme_primary));
        Setting::set('theme.secondary', ThemeColors::normalizeHex($this->theme_secondary));
        Setting::set('theme.tertiary', ThemeColors::normalizeHex($this->theme_tertiary));
        Setting::set('theme.sidebar', $this->theme_sidebar);

        Cache::flush();

        session()->flash('success', 'Settings updated successfully!');
    }

    /** Send one message straight away to check the gateway settings. */
    public function sendTestSms(): void
    {
        $this->validate(['sms_test_phone' => 'required|string|max:20']);
        $this->save();

        $log = app(SmsService::class)->send(
            $this->sms_test_phone,
            'Test message from '.($this->company_name ?: config('app.name')).'. SMS is working.',
            auth()->user(),
            'test',
            now: true,
        )->fresh();

        $status = $log->new_values['status'] ?? 'logged';
        session()->flash($status === 'failed' ? 'error' : 'success', match ($status) {
            'sent' => 'Test SMS sent. Check the phone.',
            'failed' => 'The gateway refused the message: '.mb_substr((string) ($log->new_values['response'] ?? ''), 0, 200),
            default => 'SMS is off or the gateway URL is empty, so the message was only logged.',
        });
    }

    public function render()
    {
        $logoUrl = Setting::get('company.logo');

        return view('livewire.settings.general-settings', compact('logoUrl'))
            ->layout('layouts.app', ['title' => 'General Settings']);
    }
}

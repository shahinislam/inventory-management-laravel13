<?php

namespace App\Livewire\Settings;

use App\Models\Media;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class GeneralSettings extends Component
{
    // Company
    public string $company_name    = '';
    public ?int $company_logo_id   = null;
    public string $company_email   = '';
    public string $company_phone   = '';
    public string $company_address = '';
    public string $company_tax_number = '';

    // Currency
    public string $currency_symbol   = '';
    public string $currency_code     = '';
    public string $currency_position = 'before';
    public string $currency_decimals = '2';

    // Tax
    public string $tax_rate      = '0';
    public bool $tax_inclusive   = false;

    // Invoice
    public string $invoice_prefix = '';
    public string $invoice_footer = '';
    public string $invoice_terms  = '';

    // Notification
    public bool $notification_low_stock = true;
    public bool $notification_expiry    = true;
    public string $notification_days    = '30';

    public bool $showMediaPicker = false;

    protected $listeners = ['select-media' => 'selectLogo'];

    public function mount(): void
    {
        $this->company_name       = Setting::get('company.name', '') ?? '';
        $this->company_logo_id    = null;
        $this->company_email      = Setting::get('company.email', '') ?? '';
        $this->company_phone      = Setting::get('company.phone', '') ?? '';
        $this->company_address    = Setting::get('company.address', '') ?? '';
        $this->company_tax_number = Setting::get('company.tax_number', '') ?? '';

        $this->currency_symbol   = Setting::get('currency.symbol', '$');
        $this->currency_code     = Setting::get('currency.code', 'USD');
        $this->currency_position = Setting::get('currency.position', 'before');
        $this->currency_decimals = (string) Setting::get('currency.decimals', 2);

        $this->tax_rate      = (string) Setting::get('tax.rate', 0);
        $this->tax_inclusive = Setting::get('tax.inclusive', false);

        $this->invoice_prefix = Setting::get('invoice.prefix', 'INV') ?? 'INV';
        $this->invoice_footer = Setting::get('invoice.footer', '') ?? '';
        $this->invoice_terms  = Setting::get('invoice.terms', '') ?? '';

        $this->notification_low_stock = Setting::get('notification.low_stock', true);
        $this->notification_expiry    = Setting::get('notification.expiry', true);
        $this->notification_days      = (string) Setting::get('notification.days', 30);
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
            'company_name'  => 'required|string|max:200',
            'company_email' => 'nullable|email',
            'invoice_prefix' => 'required|string|max:10',
        ]);

        Setting::set('company.name', $this->company_name);
        Setting::set('company.email', $this->company_email);
        Setting::set('company.phone', $this->company_phone);
        Setting::set('company.address', $this->company_address);
        Setting::set('company.tax_number', $this->company_tax_number);

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

        Cache::flush();

        session()->flash('success', 'Settings updated successfully!');
    }

    public function render()
    {
        $logoUrl = Setting::get('company.logo');

        return view('livewire.settings.general-settings', compact('logoUrl'))
            ->layout('layouts.app', ['title' => 'General Settings']);
    }
}

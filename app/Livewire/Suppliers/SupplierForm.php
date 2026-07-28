<?php

namespace App\Livewire\Suppliers;

use App\Models\Media;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SupplierForm extends Component
{
    public ?Supplier $supplier = null;

    public string $name = '';

    public string $company_name = '';

    public string $email = '';

    public string $phone = '';

    public string $alternative_phone = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $postal_code = '';

    public string $tax_number = '';

    public string $payment_terms = 'net_30';

    public string $credit_limit = '0';

    public ?int $media_id = null;

    public bool $is_active = true;

    public string $notes = '';

    public bool $showMediaPicker = false;

    protected $listeners = ['select-media' => 'selectMedia'];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'company_name' => 'nullable|string|max:200',
            'email' => 'nullable|email|max:150|unique:suppliers,email,'.($this->supplier?->id ?? 'NULL'),
            'phone' => 'nullable|string|max:20',
            'alternative_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_number' => 'nullable|string|max:50',
            'payment_terms' => 'required|in:immediate,net_15,net_30,net_60',
            'credit_limit' => 'nullable|numeric|min:0',
            'media_id' => 'nullable|exists:media,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier?->exists) {
            $this->supplier = $supplier;
            $this->name = $supplier->name;
            $this->company_name = $supplier->company_name ?? '';
            $this->email = $supplier->email ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->alternative_phone = $supplier->alternative_phone ?? '';
            $this->address = $supplier->address ?? '';
            $this->city = $supplier->city ?? '';
            $this->state = $supplier->state ?? '';
            $this->country = $supplier->country ?? '';
            $this->postal_code = $supplier->postal_code ?? '';
            $this->tax_number = $supplier->tax_number ?? '';
            $this->payment_terms = $supplier->payment_terms;
            $this->credit_limit = $supplier->credit_limit;
            $this->media_id = $supplier->media_id;
            $this->is_active = $supplier->is_active;
            $this->notes = $supplier->notes ?? '';
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['email'] = $data['email'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $data['alternative_phone'] = $data['alternative_phone'] ?: null;
        $data['address'] = $data['address'] ?: null;
        $data['city'] = $data['city'] ?: null;
        $data['state'] = $data['state'] ?: null;
        $data['country'] = $data['country'] ?: null;
        $data['postal_code'] = $data['postal_code'] ?: null;
        $data['tax_number'] = $data['tax_number'] ?: null;
        $data['notes'] = $data['notes'] ?: null;
        $data['credit_limit'] = $data['credit_limit'] !== '' ? (float) $data['credit_limit'] : 0;

        if ($this->supplier?->exists) {
            $this->supplier->update($data);
            $message = 'Supplier updated successfully!';
        } else {
            Supplier::create($data);
            $message = 'Supplier created successfully!';
        }

        Cache::forget('suppliers_list');
        session()->flash('success', $message);
        $this->redirect(route('suppliers.index'), navigate: true);
    }

    public function selectMedia(int $mediaId): void
    {
        $this->media_id = $mediaId;
        $this->showMediaPicker = false;
    }

    public function removeMedia(): void
    {
        $this->media_id = null;
    }

    public function render()
    {
        $media = $this->media_id ? Media::find($this->media_id) : null;

        return view('livewire.suppliers.supplier-form', compact('media'))
            ->layout('layouts.app', ['title' => $this->supplier?->exists ? 'Edit Supplier' : 'Add Supplier']);
    }
}

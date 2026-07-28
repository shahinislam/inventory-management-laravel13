<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\Media;
use Livewire\Component;

class CustomerForm extends Component
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $alternative_phone = '';

    public string $date_of_birth = '';

    public string $gender = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $postal_code = '';

    public string $tax_number = '';

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
            'email' => 'nullable|email|max:150|unique:customers,email,'.($this->customer?->id ?? 'NULL'),
            'phone' => 'nullable|string|max:20',
            'alternative_phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_number' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0',
            'media_id' => 'nullable|exists:media,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customer = $customer;
            $this->name = $customer->name;
            $this->email = $customer->email ?? '';
            $this->phone = $customer->phone ?? '';
            $this->alternative_phone = $customer->alternative_phone ?? '';
            $this->date_of_birth = $customer->date_of_birth?->format('Y-m-d') ?? '';
            $this->gender = $customer->gender ?? '';
            $this->address = $customer->address ?? '';
            $this->city = $customer->city ?? '';
            $this->state = $customer->state ?? '';
            $this->country = $customer->country ?? '';
            $this->postal_code = $customer->postal_code ?? '';
            $this->tax_number = $customer->tax_number ?? '';
            $this->credit_limit = $customer->credit_limit;
            $this->media_id = $customer->media_id;
            $this->is_active = $customer->is_active;
            $this->notes = $customer->notes ?? '';
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['email'] = $data['email'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $data['alternative_phone'] = $data['alternative_phone'] ?: null;
        $data['date_of_birth'] = $data['date_of_birth'] ?: null;
        $data['gender'] = $data['gender'] ?: null;
        $data['address'] = $data['address'] ?: null;
        $data['city'] = $data['city'] ?: null;
        $data['state'] = $data['state'] ?: null;
        $data['country'] = $data['country'] ?: null;
        $data['postal_code'] = $data['postal_code'] ?: null;
        $data['tax_number'] = $data['tax_number'] ?: null;
        $data['notes'] = $data['notes'] ?: null;
        $data['credit_limit'] = $data['credit_limit'] !== '' ? (float) $data['credit_limit'] : 0;

        if ($this->customer?->exists) {
            $this->customer->update($data);
            $message = 'Customer updated successfully!';
        } else {
            Customer::create($data);
            $message = 'Customer created successfully!';
        }

        session()->flash('success', $message);
        $this->redirect(route('customers.index'), navigate: true);
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

        return view('livewire.customers.customer-form', compact('media'))
            ->layout('layouts.app', ['title' => $this->customer?->exists ? 'Edit Customer' : 'Add Customer']);
    }
}

<?php

namespace App\Livewire\Warehouses;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Livewire\Component;

class WarehouseForm extends Component
{
    public ?Warehouse $warehouse = null;

    public string $name = '';

    public string $code = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $postal_code = '';

    public string $phone = '';

    public string $email = '';

    public ?int $manager_id = null;

    public string $capacity = '';

    public bool $is_default = false;

    public bool $is_active = true;

    public string $notes = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:50|unique:warehouses,code,'.($this->warehouse?->id ?? 'NULL'),
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'manager_id' => 'nullable|exists:users,id',
            'capacity' => 'nullable|integer|min:0',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?Warehouse $warehouse = null): void
    {
        if ($warehouse?->exists) {
            $this->warehouse = $warehouse;
            $this->name = $warehouse->name;
            $this->code = $warehouse->code;
            $this->address = $warehouse->address ?? '';
            $this->city = $warehouse->city ?? '';
            $this->state = $warehouse->state ?? '';
            $this->country = $warehouse->country ?? '';
            $this->postal_code = $warehouse->postal_code ?? '';
            $this->phone = $warehouse->phone ?? '';
            $this->email = $warehouse->email ?? '';
            $this->manager_id = $warehouse->manager_id;
            $this->capacity = $warehouse->capacity ?? '';
            $this->is_default = $warehouse->is_default;
            $this->is_active = $warehouse->is_active;
            $this->notes = $warehouse->notes ?? '';
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['code'] = $data['code'] ?: 'WH-'.strtoupper(Str::random(6));
        $data['address'] = $data['address'] ?: null;
        $data['city'] = $data['city'] ?: null;
        $data['state'] = $data['state'] ?: null;
        $data['country'] = $data['country'] ?: null;
        $data['postal_code'] = $data['postal_code'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $data['email'] = $data['email'] ?: null;
        $data['capacity'] = $data['capacity'] !== '' ? (int) $data['capacity'] : null;
        $data['notes'] = $data['notes'] ?: null;

        // If setting as default, unset other defaults
        if ($data['is_default']) {
            Warehouse::where('is_default', true)
                ->when($this->warehouse, fn ($q) => $q->where('id', '!=', $this->warehouse->id))
                ->update(['is_default' => false]);
            $data['is_active'] = true;
        }

        // Prevent removing default status if it's the only warehouse
        if ($this->warehouse?->is_default && ! $data['is_default']) {
            $otherDefault = Warehouse::where('is_default', true)
                ->where('id', '!=', $this->warehouse->id)
                ->exists();

            if (! $otherDefault) {
                session()->flash('error', 'You must set another warehouse as default first.');
                $data['is_default'] = true;
            }
        }

        if ($this->warehouse?->exists) {
            $this->warehouse->update($data);
            $message = 'Warehouse updated successfully!';
        } else {
            Warehouse::create($data);
            $message = 'Warehouse created successfully!';
        }

        session()->flash('success', $message);
        $this->redirect(route('warehouses.index'), navigate: true);
    }

    public function render()
    {
        $managers = User::whereIn('role', ['admin', 'manager'])->active()->get();

        return view('livewire.warehouses.warehouse-form', compact('managers'))
            ->layout('layouts.app', ['title' => $this->warehouse?->exists ? 'Edit Warehouse' : 'Add Warehouse']);
    }
}

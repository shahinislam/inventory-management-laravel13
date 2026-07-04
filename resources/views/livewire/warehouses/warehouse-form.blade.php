<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('warehouses.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->warehouse?->exists ? 'Edit Warehouse' : 'Add Warehouse' }}</flux:heading>
            <flux:text class="mt-1">{{ $this->warehouse?->exists ? 'Update warehouse details' : 'Create a new storage location' }}</flux:text>
        </div>
    </div>

    {{-- Error Message --}}
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="save">
        <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

            {{-- Left Column --}}
            <div class="space-y-6">

                {{-- Basic Info --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Basic Information</flux:heading>

                    <div class="space-y-4">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <flux:field>
                                <flux:label>Warehouse Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="name" placeholder="e.g. Main Warehouse" />
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Code</flux:label>
                                <flux:input wire:model="code" placeholder="Auto-generated if empty" />
                                <flux:error name="code" />
                            </flux:field>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <flux:field>
                                <flux:label>Phone</flux:label>
                                <flux:input wire:model="phone" placeholder="+880 1234-567890" />
                                <flux:error name="phone" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="email" type="email" placeholder="warehouse@example.com" />
                                <flux:error name="email" />
                            </flux:field>
                        </div>
                    </div>
                </flux:card>

                {{-- Address --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Address</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Street Address</flux:label>
                            <flux:textarea wire:model="address" placeholder="Street address" rows="2" />
                            <flux:error name="address" />
                        </flux:field>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <flux:field>
                                <flux:label>City</flux:label>
                                <flux:input wire:model="city" placeholder="City" />
                                <flux:error name="city" />
                            </flux:field>

                            <flux:field>
                                <flux:label>State / Division</flux:label>
                                <flux:input wire:model="state" placeholder="State or division" />
                                <flux:error name="state" />
                            </flux:field>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <flux:field>
                                <flux:label>Country</flux:label>
                                <flux:input wire:model="country" placeholder="Country" />
                                <flux:error name="country" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Postal Code</flux:label>
                                <flux:input wire:model="postal_code" placeholder="Postal / ZIP code" />
                                <flux:error name="postal_code" />
                            </flux:field>
                        </div>
                    </div>
                </flux:card>

                {{-- Notes --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Notes</flux:heading>
                    <flux:textarea wire:model="notes" placeholder="Additional notes..." rows="3" />
                    <flux:error name="notes" />
                </flux:card>

            </div>

            {{-- Right Column --}}
            <div class="space-y-6">

                {{-- Management --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Management</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Manager</flux:label>
                            <flux:select wire:model="manager_id" placeholder="Select manager">
                                <flux:select.option value="">No Manager</flux:select.option>
                                @foreach($managers as $manager)
                                    <flux:select.option value="{{ $manager->id }}">{{ $manager->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="manager_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Storage Capacity</flux:label>
                            <flux:input wire:model="capacity" type="number" min="0" placeholder="Max units" />
                            <flux:error name="capacity" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Status --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Settings</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Default Warehouse</flux:label>
                                <flux:switch wire:model="is_default" />
                            </div>
                        </flux:field>

                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Active</flux:label>
                                <flux:switch wire:model="is_active" :disabled="$is_default" />
                            </div>
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Actions --}}
                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->warehouse?->exists ? 'Update Warehouse' : 'Save Warehouse' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('warehouses.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

</div>

<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('suppliers.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->supplier?->exists ? 'Edit Supplier' : 'Add Supplier' }}</flux:heading>
            <flux:text class="mt-1">{{ $this->supplier?->exists ? 'Update supplier details' : 'Create a new supplier' }}</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

            {{-- Left Column --}}
            <div class="space-y-6">

                {{-- Basic Info --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Basic Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Supplier Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="name" placeholder="Supplier name" />
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Company Name</flux:label>
                                <flux:input wire:model="company_name" placeholder="Company / business name" />
                                <flux:error name="company_name" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="email" type="email" placeholder="email@example.com" />
                                <flux:error name="email" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Phone</flux:label>
                                <flux:input wire:model="phone" placeholder="+880 1234-567890" />
                                <flux:error name="phone" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Alternative Phone</flux:label>
                            <flux:input wire:model="alternative_phone" placeholder="Secondary contact number" />
                            <flux:error name="alternative_phone" />
                        </flux:field>
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

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                {{-- Financial --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Financial Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Tax Number</flux:label>
                                <flux:input wire:model="tax_number" placeholder="VAT / Tax ID" />
                                <flux:error name="tax_number" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Payment Terms</flux:label>
                                <flux:select wire:model="payment_terms">
                                    <flux:select.option value="immediate">Immediate</flux:select.option>
                                    <flux:select.option value="net_15">Net 15 Days</flux:select.option>
                                    <flux:select.option value="net_30">Net 30 Days</flux:select.option>
                                    <flux:select.option value="net_60">Net 60 Days</flux:select.option>
                                </flux:select>
                                <flux:error name="payment_terms" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Credit Limit</flux:label>
                            <flux:input wire:model="credit_limit" type="number" step="0.01" min="0" placeholder="0.00" prefix="৳" />
                            <flux:error name="credit_limit" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Notes --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Notes</flux:heading>
                    <flux:textarea wire:model="notes" placeholder="Additional notes about this supplier..." rows="3" />
                    <flux:error name="notes" />
                </flux:card>

            </div>

            {{-- Right Column --}}
            <div class="space-y-6">

                {{-- Logo --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Supplier Logo</flux:heading>

                    @if($media)
                        <div class="relative">
                            <img src="{{ $media->file_url }}" class="w-full rounded-lg object-cover aspect-square" />
                            <flux:button
                                icon="x-mark" variant="ghost" size="sm" square
                                class="absolute top-2 right-2 bg-white dark:bg-zinc-800"
                                wire:click="removeMedia" type="button"
                            />
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="$set('showMediaPicker', true)"
                            class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 p-6 text-zinc-400 hover:border-zinc-400 dark:border-zinc-700"
                        >
                            <flux:icon name="photo" class="size-8" />
                            <flux:text class="text-sm">Click to select logo</flux:text>
                        </button>
                    @endif
                </flux:card>

                {{-- Status --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Status</flux:heading>
                    <flux:field>
                        <div class="flex items-center justify-between">
                            <flux:label>Active</flux:label>
                            <flux:switch wire:model="is_active" />
                        </div>
                    </flux:field>
                </flux:card>

                {{-- Actions --}}
                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->supplier?->exists ? 'Update Supplier' : 'Save Supplier' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('suppliers.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

    {{-- Media Picker Modal --}}
    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading class="mb-4">Select Logo</flux:heading>
            <livewire:media.media-picker wire:select-media="selectMedia" />
        </div>
    </flux:modal>

</div>

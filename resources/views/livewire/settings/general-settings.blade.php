<div class="p-6">

    <div class="mb-6">
        <flux:heading size="xl">General Settings</flux:heading>
        <flux:text class="mt-1">Configure your shop & application preferences</flux:text>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <form wire:submit="save">
        <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

            <div class="space-y-6">

                {{-- Company Info --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Company Information</flux:heading>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Company Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="company_name" placeholder="Your shop name" />
                            <flux:error name="company_name" />
                        </flux:field>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="company_email" type="email" placeholder="shop@example.com" />
                                <flux:error name="company_email" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Phone</flux:label>
                                <flux:input wire:model="company_phone" placeholder="+880 1234-567890" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Address</flux:label>
                            <flux:textarea wire:model="company_address" rows="2" placeholder="Shop address" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tax / VAT Number</flux:label>
                            <flux:input wire:model="company_tax_number" placeholder="Tax registration number" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Currency --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Currency Settings</flux:heading>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                        <flux:field>
                            <flux:label>Symbol</flux:label>
                            <flux:input wire:model="currency_symbol" placeholder="৳" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Currency Code</flux:label>
                            <flux:input wire:model="currency_code" placeholder="BDT" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Symbol Position</flux:label>
                            <flux:select wire:model="currency_position">
                                <flux:select.option value="before">Before amount (৳100)</flux:select.option>
                                <flux:select.option value="after">After amount (100৳)</flux:select.option>
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Decimal Places</flux:label>
                            <flux:select wire:model="currency_decimals">
                                <flux:select.option value="0">0 (100)</flux:select.option>
                                <flux:select.option value="2">2 (100.00)</flux:select.option>
                            </flux:select>
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Tax --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Tax Settings</flux:heading>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:end">
                        <flux:field>
                            <flux:label>Default Tax Rate (%)</flux:label>
                            <flux:input wire:model="tax_rate" type="number" step="0.01" min="0" suffix="%" />
                        </flux:field>
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Tax Inclusive Pricing</flux:label>
                                <flux:switch wire:model="tax_inclusive" />
                            </div>
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Invoice --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Invoice Settings</flux:heading>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Invoice Number Prefix</flux:label>
                            <flux:input wire:model="invoice_prefix" placeholder="INV" />
                            <flux:error name="invoice_prefix" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Invoice Footer Text</flux:label>
                            <flux:input wire:model="invoice_footer" placeholder="Thank you for your business!" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Terms & Conditions</flux:label>
                            <flux:textarea wire:model="invoice_terms" rows="3" placeholder="Payment terms, return policy etc..." />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Notifications --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Notification Settings</flux:heading>
                    <div class="space-y-4">
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Low Stock Alerts</flux:label>
                                <flux:switch wire:model="notification_low_stock" />
                            </div>
                        </flux:field>
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Expiry Date Warnings</flux:label>
                                <flux:switch wire:model="notification_expiry" />
                            </div>
                        </flux:field>
                        <flux:field>
                            <flux:label>Expiry Warning Days Before</flux:label>
                            <flux:input wire:model="notification_days" type="number" min="1" suffix="days" />
                        </flux:field>
                    </div>
                </flux:card>

            </div>

            {{-- Right: Logo --}}
            <div class="space-y-6">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Company Logo</flux:heading>
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="w-full rounded-lg object-contain bg-zinc-50 dark:bg-zinc-800" style="aspect-ratio:1" />
                    @else
                        <div class="flex items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-700" style="aspect-ratio:1">
                            <flux:icon name="building-office" class="size-12 text-zinc-300" />
                        </div>
                    @endif
                    <flux:button type="button" variant="outline" class="w-full mt-3" icon="photo" wire:click="$set('showMediaPicker', true)">
                        Change Logo
                    </flux:button>
                </flux:card>

                <flux:card class="p-6">
                    <flux:button type="submit" class="w-full" icon="check">Save All Settings</flux:button>
                </flux:card>
            </div>

        </div>
    </form>

    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading class="mb-4">Select Logo</flux:heading>
            <livewire:media.media-picker wire:select-media="selectLogo" />
        </div>
    </flux:modal>

</div>

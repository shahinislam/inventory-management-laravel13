<div class="p-4">

    <div class="mb-6">
        <flux:heading size="xl">General Settings</flux:heading>
        <flux:text class="mt-1">Configure your shop & application preferences</flux:text>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

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

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
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

                {{-- Brand Colours --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-1">Brand Colours</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">
                        Primary drives buttons, links and the active menu. Each colour is expanded into a full shade
                        range automatically — pick the colours from your logo.
                    </flux:text>

                    <div class="space-y-4">
                        @foreach ([
                            ['theme_primary', 'Primary', 'Buttons, links, focus rings, active nav'],
                            ['theme_secondary', 'Secondary', 'Supporting accents and highlights'],
                            ['theme_tertiary', 'Tertiary', 'Charts and extra data series'],
                        ] as [$field, $label, $hint])
                            <flux:field>
                                <flux:label>{{ $label }}</flux:label>
                                <div class="flex items-center gap-3">
                                    {{-- Native swatch picker and a hex field, bound to the same property so
                                         either can drive the value. --}}
                                    <input type="color" wire:model.live="{{ $field }}"
                                        class="size-10 shrink-0 cursor-pointer rounded-lg border border-zinc-200 bg-transparent p-1 dark:border-zinc-700"
                                        aria-label="{{ $label }} colour picker" />
                                    <flux:input wire:model.live="{{ $field }}" class="font-mono" placeholder="#4f46e5" />
                                </div>
                                <flux:text class="text-xs text-zinc-500">{{ $hint }}</flux:text>
                                <flux:error name="{{ $field }}" />
                            </flux:field>
                        @endforeach

                        {{-- Live preview: reflects the pickers immediately, before saving. --}}
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:text class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">
                                Preview</flux:text>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach (['theme_primary' => 'Primary', 'theme_secondary' => 'Secondary', 'theme_tertiary' => 'Tertiary'] as $field => $label)
                                    <span
                                        class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold text-white"
                                        style="background-color: {{ $$field }}">
                                        {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                            <flux:text class="mt-3 text-xs text-zinc-500">
                                Save to apply across the app.
                            </flux:text>
                        </div>
                    </div>
                </flux:card>

            </div>

            {{-- Right: Logo --}}
            <div class="space-y-6">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Company Logo</flux:heading>
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="aspect-square w-full rounded-lg object-contain bg-zinc-50 dark:bg-zinc-800" />
                    @else
                        <div class="flex aspect-square items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-700">
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

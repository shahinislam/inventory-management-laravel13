<div class="p-4">

    <div class="mb-6">
        <flux:heading size="xl">General Settings</flux:heading>
        <flux:text class="mt-1">Configure your shop & application preferences</flux:text>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif
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

                        <flux:field>
                            <flux:label>VAT BIN</flux:label>
                            <flux:input wire:model="company_bin" placeholder="e.g. 000123456-0101" />
                            <flux:description>Business Identification Number, printed on the Mushak 6.3 invoice.</flux:description>
                            <flux:error name="company_bin" />
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
                        <flux:field class="sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:label>Mushak 6.3 VAT invoice</flux:label>
                                    <flux:description>Adds a “Mushak 6.3” print button to invoices and the POS. Set the VAT BIN above.</flux:description>
                                </div>
                                <flux:switch wire:model="mushak_enabled" />
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

                {{-- SMS --}}
                <flux:card class="p-6">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <flux:heading>SMS</flux:heading>
                            <flux:text class="text-sm">Works with most SMS gateways (BulkSMSBD, SSL Wireless, Alpha SMS…). Copy the URL and parameter names from your gateway's API page. While off, messages are only logged.</flux:text>
                        </div>
                        <flux:switch wire:model.live="sms_enabled" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_8rem]">
                        <flux:field>
                            <flux:label>Gateway URL</flux:label>
                            <flux:input wire:model="sms_gateway_url" placeholder="https://bulksmsbd.net/api/smsapi" />
                            <flux:error name="sms_gateway_url" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Method</flux:label>
                            <flux:select wire:model="sms_http_method">
                                <flux:select.option value="POST">POST</flux:select.option>
                                <flux:select.option value="GET">GET</flux:select.option>
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>API key</flux:label>
                            <flux:input wire:model="sms_api_key" type="password" viewable />
                        </flux:field>
                        <flux:field>
                            <flux:label>Sender ID</flux:label>
                            <flux:input wire:model="sms_sender_id" placeholder="e.g. 8809617..." />
                        </flux:field>
                    </div>
                    <details class="mt-4">
                        <summary class="text-sm text-zinc-500">Parameter names (change only if your gateway uses different ones)</summary>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <flux:input wire:model="sms_param_to" label="Phone" size="sm" />
                            <flux:input wire:model="sms_param_message" label="Message" size="sm" />
                            <flux:input wire:model="sms_param_key" label="API key" size="sm" />
                            <flux:input wire:model="sms_param_sender" label="Sender" size="sm" />
                        </div>
                    </details>
                    <div class="mt-4 space-y-3">
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>Send a thank-you SMS after each member sale</flux:label>
                                <flux:switch wire:model="sms_send_receipt" />
                            </div>
                        </flux:field>
                        <flux:field>
                            <flux:label>Sale message</flux:label>
                            <flux:textarea wire:model="sms_receipt_template" rows="2" />
                            <flux:description>Placeholders: {name} {invoice} {total} {spent} {shop}</flux:description>
                        </flux:field>
                        <flux:field>
                            <flux:label>Reward message</flux:label>
                            <flux:textarea wire:model="sms_reward_template" rows="2" />
                            <flux:description>Placeholders: {name} {reward} {invoice} {shop}</flux:description>
                        </flux:field>
                        <div class="flex items-end gap-2">
                            <flux:input wire:model="sms_test_phone" label="Send a test to" placeholder="01XXXXXXXXX" class="flex-1" />
                            <flux:button wire:click="sendTestSms" type="button" icon="paper-airplane">Send test</flux:button>
                        </div>
                        <flux:error name="sms_test_phone" />
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

                        {{-- Sidebar style --}}
                        <flux:field>
                            <flux:label>Sidebar style</flux:label>
                            <flux:text class="text-xs text-zinc-500">Colour of the left menu — applies as soon as you pick one. Brand uses your primary colour.</flux:text>
                            <div class="mt-2 grid grid-cols-3 gap-3">
                                @foreach ([
                                    'light' => ['Light', 'background-color:#fafafa', '#18181b', 'background-color:'.$theme_primary.'22;color:'.$theme_primary],
                                    'dark' => ['Dark', 'background-color:#18181b', '#ffffffcc', 'background-color:'.$theme_primary.';color:#fff'],
                                    'brand' => ['Brand', 'background-color:color-mix(in oklch, '.$theme_primary.' 70%, black)', '#ffffffd9', 'background-color:#fff;color:'.$theme_primary],
                                ] as $style => [$label, $bg, $text, $pill])
                                    <label wire:key="sidebar-style-{{ $style }}"
                                        class="cursor-pointer rounded-lg border-2 p-2 transition {{ $theme_sidebar === $style ? 'border-primary-600' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700' }}">
                                        <input type="radio" wire:model.live="theme_sidebar" value="{{ $style }}" class="sr-only" />
                                        <div class="space-y-1 rounded-md p-2" style="{{ $bg }}">
                                            <div class="h-1.5 w-3/4 rounded" style="background-color: {{ $text }}"></div>
                                            <div class="h-3 rounded px-1 text-[8px] font-bold leading-3" style="{{ $pill }}">Active</div>
                                            <div class="h-1.5 w-2/3 rounded" style="background-color: {{ $text }}"></div>
                                            <div class="h-1.5 w-1/2 rounded" style="background-color: {{ $text }}"></div>
                                        </div>
                                        <div class="mt-1.5 text-center text-sm font-semibold">{{ $label }}</div>
                                    </label>
                                @endforeach
                            </div>
                            <flux:error name="theme_sidebar" />
                        </flux:field>
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

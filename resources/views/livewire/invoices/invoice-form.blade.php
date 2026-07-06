<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('invoices.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->invoice?->exists ? 'Edit Invoice' : 'New Invoice' }}</flux:heading>
            <flux:text class="mt-1">
                {{ $this->invoice?->exists ? $this->invoice->invoice_number : 'Create a manual invoice' }}</flux:text>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

        {{-- Left Column --}}
        <div class="space-y-6">

            {{-- Customer --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Customer</flux:heading>

                {{-- Customer Search --}}
                <div style="position:relative" class="mb-4" x-data="{ open: true }" x-on:click.outside="open = false">
                    <flux:input wire:model.live.debounce.200ms="customerSearch"
                        placeholder="Search existing customer..." icon="user" x-on:focus="open = true" />
                    @if ($customer_id)
                        <flux:button icon="x-mark" variant="ghost" size="sm" square
                            class="absolute right-2 top-1/2 -translate-y-1/2" wire:click="clearCustomer"
                            type="button" />
                    @endif
                    @if ($this->customerResults->count() > 0)
                        <div x-show="open"
                            class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($this->customerResults as $cust)
                                <button type="button" wire:click="selectCustomer({{ $cust->id }})"
                                    x-on:click="open = false" wire:key="cust-{{ $cust->id }}"
                                    class="flex w-full items-center justify-between px-3 py-2">
                                    <flux:text class="text-sm">{{ $cust->name }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">{{ $cust->phone }}</flux:text>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <flux:field>
                        <flux:label>Name <flux:badge color="red" size="sm">Required</flux:badge>
                        </flux:label>
                        <flux:input wire:model="customer_name" placeholder="Customer name" />
                        <flux:error name="customer_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Phone</flux:label>
                        <flux:input wire:model="customer_phone" placeholder="Phone number" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="customer_email" type="email" placeholder="Email address" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Address</flux:label>
                        <flux:input wire:model="customer_address" placeholder="Address" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Items --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Invoice Items</flux:heading>

                {{-- Product Search --}}
                {{-- Product Search --}}
                <div style="position:relative" class="mb-4" x-data="{
                    open: true,
                    highlight: 0,
                    get items() { return this.$refs.productResultsList ? [...this.$refs.productResultsList.querySelectorAll('[data-search-result]')] : [] },
                    get count() { return this.items.length },
                    moveDown() {
                        if (this.count > 0) {
                            this.highlight = (this.highlight + 1) % this.count;
                            this.items[this.highlight]?.scrollIntoView({ block: 'nearest' });
                        }
                    },
                    moveUp() {
                        if (this.count > 0) {
                            this.highlight = (this.highlight - 1 + this.count) % this.count;
                            this.items[this.highlight]?.scrollIntoView({ block: 'nearest' });
                        }
                    },
                    selectCurrent() { const el = this.items[this.highlight]; if (el) el.click(); }
                }" x-on:click.outside="open = false">
                    <flux:input wire:model.live.debounce.150ms="productSearch"
                        placeholder="Search product by name or SKU..." icon="magnifying-glass" autocomplete="off"
                        name="invoice-product-search-nofill" x-on:focus="open = true"
                        x-on:input="open = true; highlight = 0" x-on:keydown.arrow-down.prevent="moveDown()"
                        x-on:keydown.arrow-up.prevent="moveUp()"
                        x-on:keydown.enter.prevent="selectCurrent(); open = false" />
                    @if ($this->productResults->count() > 0)
                        <div x-ref="productResultsList" x-show="open"
                            class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            style="max-height:320px;overflow-y:auto">
                            @foreach ($this->productResults as $i => $product)
                                <button type="button" data-search-result wire:click="addProduct({{ $product->id }})"
                                    x-on:click="open = false" x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="presult-{{ $product->id }}"
                                    class="flex w-full items-center justify-between px-3 py-2 transition-colors"
                                    x-bind:style="highlight === {{ $i }} ? 'background-color: rgb(37 99 235 / 0.15)' : ''">
                                    <div>
                                        <flux:text
                                            x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' : ''"
                                            class="text-sm font-medium">{{ $product->name }}</flux:text>
                                        <flux:text class="text-xs text-zinc-400">{{ $product->sku }}</flux:text>
                                    </div>
                                    <flux:text
                                        x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' : ''"
                                        class="text-sm font-medium">${{ number_format($product->selling_price, 2) }}
                                    </flux:text>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Items Table --}}
                <table style="width:100%;font-size:0.875rem">
                    <thead>
                        <tr style="border-bottom:1px solid var(--color-zinc-200, #e4e4e7)">
                            <th style="text-align:left;padding:0.5rem;font-weight:500">Product</th>
                            <th style="text-align:right;padding:0.5rem;font-weight:500;width:90px">Qty</th>
                            <th style="text-align:right;padding:0.5rem;font-weight:500;width:110px">Price</th>
                            <th style="text-align:right;padding:0.5rem;font-weight:500;width:90px">Discount</th>
                            <th style="text-align:right;padding:0.5rem;font-weight:500;width:80px">Tax%</th>
                            <th style="text-align:right;padding:0.5rem;font-weight:500;width:100px">Total</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $index => $item)
                            @php
                                $line = (float) $item['quantity'] * (float) $item['unit_price'];
                                $disc = (float) $item['discount'] * (float) $item['quantity'];
                                $tax = ($line - $disc) * ((float) $item['tax_rate'] / 100);
                                $total = $line - $disc + $tax;
                            @endphp
                            <tr wire:key="item-{{ $index }}"
                                style="border-bottom:1px solid var(--color-zinc-100, #f4f4f5)">
                                <td style="padding:0.5rem">
                                    <flux:text class="font-medium">{{ $item['name'] }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">{{ $item['sku'] }}</flux:text>
                                    @if (!empty($item['promotion_label']))
                                        <flux:badge size="sm" color="green" class="mt-1">
                                            {{ $item['promotion_label'] }}</flux:badge>
                                    @endif
                                </td>
                                <td style="padding:0.5rem">
                                    <flux:input wire:model.live="items.{{ $index }}.quantity" type="number"
                                        min="1" size="sm" />
                                </td>
                                <td style="padding:0.5rem">
                                    <flux:input wire:model.live="items.{{ $index }}.unit_price" type="number"
                                        step="0.01" size="sm" />
                                </td>
                                <td style="padding:0.5rem">
                                    <flux:input wire:model.live="items.{{ $index }}.discount" type="number"
                                        step="0.01" size="sm" />
                                </td>
                                <td style="padding:0.5rem">
                                    <flux:input wire:model.live="items.{{ $index }}.tax_rate" type="number"
                                        step="0.01" size="sm" />
                                </td>
                                <td style="padding:0.5rem;text-align:right">
                                    <flux:text class="font-medium">${{ number_format($total, 2) }}</flux:text>
                                </td>
                                <td style="padding:0.5rem">
                                    <flux:button icon="trash" variant="ghost" size="sm" square
                                        wire:click="removeItem({{ $index }})" type="button" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="padding:2rem;text-align:center;color:var(--color-zinc-400)">
                                    Search products above to add items
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @error('items')
                    <flux:text class="text-sm text-red-500 mt-2">{{ $message }}</flux:text>
                @enderror
            </flux:card>

            {{-- Notes --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Notes</flux:heading>
                <flux:textarea wire:model="notes" placeholder="Additional notes..." rows="2" />
            </flux:card>
        </div>

        {{-- Right Column --}}
        <div class="space-y-6">

            {{-- Invoice Details --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Invoice Details</flux:heading>
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Invoice Date <flux:badge color="red" size="sm">Required</flux:badge>
                        </flux:label>
                        <flux:input wire:model="invoice_date" type="date" />
                        <flux:error name="invoice_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Due Date</flux:label>
                        <flux:input wire:model="due_date" type="date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Warehouse</flux:label>
                        <flux:select wire:model="warehouse_id">
                            <flux:select.option value="">No Warehouse</flux:select.option>
                            @foreach ($warehouses as $w)
                                <flux:select.option value="{{ $w->id }}">{{ $w->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model="payment_method">
                            <flux:select.option value="cash">Cash</flux:select.option>
                            <flux:select.option value="card">Card</flux:select.option>
                            <flux:select.option value="bank_transfer">Bank Transfer</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
            </flux:card>

            {{-- Summary --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Summary</flux:heading>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Subtotal</flux:text>
                        <flux:text class="font-medium">${{ number_format($this->subtotal, 2) }}</flux:text>
                    </div>
                    <flux:field>
                        <flux:label class="text-sm">Discount</flux:label>
                        <flux:input wire:model.live="discount" type="number" step="0.01" min="0"
                            prefix="৳" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="text-sm">Tax</flux:label>
                        <flux:input wire:model.live="tax" type="number" step="0.01" min="0"
                            prefix="৳" />
                    </flux:field>
                    <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                        <flux:heading>Total</flux:heading>
                        <flux:heading>${{ number_format($this->total, 2) }}</flux:heading>
                    </div>
                </div>
            </flux:card>

            {{-- Actions --}}
            <flux:card class="p-6">
                <div class="space-y-3">
                    <flux:button wire:click="saveAsPaid" class="w-full" icon="check">Save & Mark Paid</flux:button>
                    <flux:button wire:click="saveAndSend" variant="outline" class="w-full" icon="paper-airplane">
                        Save & Send</flux:button>
                    <flux:button wire:click="saveDraft" variant="ghost" class="w-full" icon="document">Save as Draft
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" href="{{ route('invoices.index') }}" wire:navigate>
                        Cancel</flux:button>
                </div>
            </flux:card>

        </div>
    </div>
</div>

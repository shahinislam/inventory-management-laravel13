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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

        {{-- Left Column --}}
        <div class="min-w-0 space-y-6">

            {{-- Customer --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Customer</flux:heading>

                {{-- Customer Search --}}
                <div class="relative mb-4" x-data="{ open: true }" x-on:click.outside="open = false">
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

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label badge="Required">Name</flux:label>
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
                <div class="relative mb-4" x-data="{
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
                            class="absolute z-10 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($this->productResults as $i => $product)
                                <button type="button" data-search-result wire:click="addProduct({{ $product->id }})"
                                    x-on:click="open = false" x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="presult-{{ $product->id }}"
                                    x-bind:data-active="highlight === {{ $i }}"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left transition-colors data-[active=true]:bg-zinc-100 dark:data-[active=true]:bg-zinc-800">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $product->name }}</div>
                                        <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $product->sku }}</div>
                                    </div>
                                    <span
                                        class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ money($product->selling_price) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Items Table. Five numeric inputs can't compress far, so the
                     table scrolls horizontally rather than crushing columns. --}}
                <div class="-mx-2 overflow-x-auto">
                    <table class="w-full min-w-3xl text-sm">
                        <thead>
                            <tr
                                class="border-b border-zinc-200 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2.5 text-left font-semibold">Product</th>
                                <th class="w-20 px-2 py-2.5 text-right font-semibold">Qty</th>
                                <th class="w-28 px-2 py-2.5 text-right font-semibold">Price</th>
                                <th class="w-24 px-2 py-2.5 text-right font-semibold">Discount</th>
                                <th class="w-20 px-2 py-2.5 text-right font-semibold">Tax %</th>
                                <th class="w-28 px-2 py-2.5 text-right font-semibold">Total</th>
                                <th class="w-10"></th>
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
                                    class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/30">
                                    <td class="px-2 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</div>
                                        <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                        @if (!empty($item['promotion_label']))
                                            <flux:badge size="sm" color="green" icon="tag" class="mt-1.5">
                                                {{ $item['promotion_label'] }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model.live="items.{{ $index }}.quantity" type="number"
                                            min="1" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model.live="items.{{ $index }}.unit_price" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model.live="items.{{ $index }}.discount" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model.live="items.{{ $index }}.tax_rate" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td
                                        class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                                        {{ money($total) }}</td>
                                    <td class="px-2 py-3">
                                        <flux:button icon="x-mark" variant="subtle" size="xs" square
                                            wire:click="removeItem({{ $index }})" type="button" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-2 py-10 text-center">
                                        <flux:icon name="magnifying-glass" class="mx-auto size-8 text-zinc-300" />
                                        <flux:text class="mt-2 text-sm text-zinc-500">Search products above to add items
                                        </flux:text>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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

        {{-- Right Column. Sticks on desktop so the running total and save
             actions stay reachable while working through a long item list. --}}
        <div class="space-y-6 lg:sticky lg:top-6 lg:self-start">

            {{-- Invoice Details --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Invoice Details</flux:heading>
                <div class="space-y-4">
                    <flux:field>
                        <flux:label badge="Required">Invoice Date</flux:label>
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
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Subtotal</span>
                        <span
                            class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->subtotal) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Discount</flux:label>
                            <flux:input wire:model.live="discount" type="number" step="0.01" min="0"
                                prefix="৳" class="tabular-nums" />
                        </flux:field>
                        <flux:field>
                            <flux:label class="text-xs">Additional Tax</flux:label>
                            <flux:input wire:model.live="tax" type="number" step="0.01" min="0" prefix="৳"
                                class="tabular-nums" />
                        </flux:field>
                    </div>

                    @if ($this->taxTotal > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Tax</span>
                            <span
                                class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->taxTotal) }}</span>
                        </div>
                    @endif

                    <div
                        class="flex items-baseline justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                        <span
                            class="text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white">{{ money($this->total) }}</span>
                    </div>
                </div>
            </flux:card>

            {{-- Actions --}}
            <flux:card class="p-6">
                <div class="space-y-3">
                    <flux:button variant="primary" wire:click="saveAsPaid" class="w-full" icon="check">Save & Mark Paid
                    </flux:button>
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

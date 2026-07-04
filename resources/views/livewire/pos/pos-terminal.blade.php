<div class="p-4" x-data="{
    focusSearch() { $refs.searchInput?.focus() },
        focusAmount() { $refs.amountInput?.focus() }
}" x-on:keydown.f2.window.prevent="focusSearch()"
    x-on:keydown.f9.window.prevent="$wire.openPaymentModal()" x-on:keydown.escape.window="$wire.clearCart()">
    {{-- Flash Messages --}}
    @if (session('error'))
        <div class="mb-3 rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 420px;gap:1rem;height:calc(100vh - 7rem)">

        {{-- ============ LEFT: PRODUCT SEARCH & GRID ============ --}}
        <div class="flex flex-col gap-3" style="min-height:0">

            {{-- Search Bar --}}
            <flux:card class="p-3">
                <div style="position:relative" x-data="{
                    open: true,
                    highlight: 0,
                    get items() { return this.$refs.resultsList ? [...this.$refs.resultsList.querySelectorAll('[data-search-result]')] : [] },
                    get count() { return this.items.length },
                    moveDown() { if (this.count > 0) this.highlight = (this.highlight + 1) % this.count },
                    moveUp() { if (this.count > 0) this.highlight = (this.highlight - 1 + this.count) % this.count },
                    selectCurrent() { const el = this.items[this.highlight]; if (el) el.click(); }
                }" x-on:click.outside="open = false">
                    <flux:input x-ref="searchInput" wire:model.live.debounce.150ms="search"
                        placeholder="Scan barcode or search product... (F2)" icon="magnifying-glass" autofocus
                        autocomplete="off" name="pos-product-search-nofill" x-on:focus="open = true"
                        x-on:input="open = true; highlight = 0" x-on:keydown.arrow-down.prevent="moveDown()"
                        x-on:keydown.arrow-up.prevent="moveUp()"
                        x-on:keydown.enter.prevent="selectCurrent(); open = false" />

                    @if ($this->searchResults->count() > 0)
                        <div x-ref="resultsList" x-show="open"
                            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                            style="max-height:320px;overflow-y:auto">
                            @foreach ($this->searchResults as $i => $product)
                                <button type="button" data-search-result wire:click="quickAdd({{ $product->id }})"
                                    x-on:click="open = false" x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="search-{{ $product->id }}"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors"
                                    x-bind:style="highlight === {{ $i }} ? 'background-color: rgb(37 99 235 / 0.15)' : ''">
                                    <div class="flex items-center gap-2">
                                        @if ($product->media)
                                            <img src="{{ $product->media->file_url }}"
                                                class="size-8 rounded object-cover" />
                                        @else
                                            <div
                                                class="flex size-8 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                                                <flux:icon name="cube" class="size-4 text-zinc-400" />
                                            </div>
                                        @endif
                                        <div>
                                            <flux:text
                                                x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' :
                                                    ''"
                                                class="text-sm font-medium">{{ $product->name }}</flux:text>
                                            <flux:text class="text-xs text-zinc-400">{{ $product->sku }} · Stock:
                                                {{ $product->quantity }}</flux:text>
                                        </div>
                                    </div>
                                    <flux:text
                                        x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' : ''"
                                        class="font-medium">${{ number_format($product->selling_price, 2) }}
                                    </flux:text>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Cart Items --}}
            <flux:card class="flex-1 p-0" style="overflow-y:auto;min-height:0">
                <table style="width:100%;font-size:0.875rem">
                    <thead style="position:sticky;top:0;background:var(--color-zinc-100, #f4f4f5);z-index:1">
                        <tr style="border-bottom:1px solid var(--color-zinc-200, #e4e4e7)">
                            <th
                                style="text-align:left;padding:0.75rem;font-weight:600;color:var(--color-zinc-700, #3f3f46)">
                                Product</th>
                            <th
                                style="text-align:center;padding:0.75rem;font-weight:600;width:140px;color:var(--color-zinc-700, #3f3f46)">
                                Qty</th>
                            <th
                                style="text-align:right;padding:0.75rem;font-weight:600;width:100px;color:var(--color-zinc-700, #3f3f46)">
                                Price</th>
                            <th
                                style="text-align:right;padding:0.75rem;font-weight:600;width:100px;color:var(--color-zinc-700, #3f3f46)">
                                Total</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cart as $index => $item)
                            @php
                                $lineTotal = $item['price'] * $item['quantity'] - $item['discount'] * $item['quantity'];
                                $lineTax = $lineTotal * ($item['tax_rate'] / 100);
                            @endphp
                            <tr wire:key="cart-{{ $index }}"
                                style="border-bottom:1px solid var(--color-zinc-100, #f4f4f5)">
                                <td style="padding:0.75rem">
                                    <flux:text class="font-medium">{{ $item['name'] }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">{{ $item['sku'] }}</flux:text>
                                    @if (!empty($item['promotion_label']))
                                        <flux:badge size="sm" color="green" class="mt-1">
                                            {{ $item['promotion_label'] }}</flux:badge>
                                    @endif
                                </td>
                                <td style="padding:0.5rem;text-align:center">
                                    <div class="flex items-center justify-center gap-1">
                                        <flux:button icon="minus" size="sm" square variant="ghost"
                                            wire:click="decrementQty({{ $index }})" type="button" />
                                        <input type="number"
                                            wire:change="updateQty({{ $index }}, $event.target.value)"
                                            value="{{ $item['quantity'] }}"
                                            class="w-12 rounded border border-zinc-200 bg-transparent text-center dark:border-zinc-700"
                                            min="1" max="{{ $item['max_quantity'] }}" />
                                        <flux:button icon="plus" size="sm" square variant="ghost"
                                            wire:click="incrementQty({{ $index }})" type="button" />
                                    </div>
                                </td>
                                <td style="padding:0.75rem;text-align:right">${{ number_format($item['price'], 2) }}
                                </td>
                                <td style="padding:0.75rem;text-align:right">
                                    <flux:text class="font-medium">${{ number_format($lineTotal + $lineTax, 2) }}
                                    </flux:text>
                                </td>
                                <td style="padding:0.5rem;text-align:right">
                                    <flux:button icon="trash" size="sm" square variant="ghost"
                                        wire:click="removeFromCart({{ $index }})" type="button" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"
                                    style="padding:3rem;text-align:center;color:var(--color-zinc-400, #a1a1aa)">
                                    <flux:icon name="shopping-cart" class="mx-auto mb-2 size-10" />
                                    Cart is empty. Scan or search to add products.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </flux:card>

        </div>

        {{-- ============ RIGHT: CUSTOMER & TOTALS ============ --}}
        <div class="flex flex-col gap-3">

            {{-- Customer --}}
            <flux:card class="p-3">
                <div style="position:relative" x-data="{ open: true }" x-on:click.outside="open = false">
                    <flux:input wire:model.live.debounce.200ms="customerSearch"
                        placeholder="Customer name or phone (optional)" icon="user" x-on:focus="open = true" />
                    @if ($customer_id)
                        <flux:button icon="x-mark" variant="ghost" size="sm" square
                            class="absolute right-2 top-1/2 -translate-y-1/2" wire:click="clearCustomer"
                            type="button" />
                    @endif

                    @if ($this->customerResults->count() > 0 && !$customer_id)
                        <div x-show="open"
                            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($this->customerResults as $cust)
                                <button type="button" wire:click="selectCustomer({{ $cust->id }})"
                                    x-on:click="open = false" wire:key="cust-{{ $cust->id }}"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left">
                                    <flux:text class="text-sm">{{ $cust->name }}</flux:text>
                                    <flux:text class="text-xs text-zinc-400">{{ $cust->phone }}</flux:text>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Totals --}}
            <flux:card class="flex-1 p-4">
                <flux:heading class="mb-4">Order Summary</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Items</flux:text>
                        <flux:text class="font-medium">{{ count($cart) }}</flux:text>
                    </div>

                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Subtotal</flux:text>
                        <flux:text class="font-medium">${{ number_format($this->cartSubtotal, 2) }}</flux:text>
                    </div>

                    @if ($this->cartItemDiscountTotal > 0)
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Item Discounts</flux:text>
                            <flux:text class="text-sm text-green-500">
                                -${{ number_format($this->cartItemDiscountTotal, 2) }}</flux:text>
                        </div>
                    @endif

                    <flux:field>
                        <flux:label class="text-sm">Extra Discount</flux:label>
                        <flux:input wire:model.live="discount" type="number" step="0.01" min="0"
                            prefix="৳" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-sm">Additional Tax</flux:label>
                        <flux:input wire:model.live="tax" type="number" step="0.01" min="0"
                            prefix="৳" />
                    </flux:field>

                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">Total</flux:heading>
                            <flux:heading size="lg">${{ number_format($this->cartTotal, 2) }}</flux:heading>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="mt-6 space-y-2">
                    <flux:button wire:click="openPaymentModal" class="w-full" icon="check-circle">
                        Checkout (F9)
                    </flux:button>
                    <flux:button wire:click="clearCart" variant="ghost" class="w-full" icon="x-mark">
                        Clear Cart (ESC)
                    </flux:button>
                </div>

                {{-- Shortcuts Help --}}
                <div class="mt-4 rounded-lg bg-zinc-50 p-3 text-xs text-zinc-400 dark:bg-zinc-800">
                    <div class="flex justify-between"><span>Focus Search</span><span>F2</span></div>
                    <div class="flex justify-between"><span>Navigate Results</span><span>↑ ↓</span></div>
                    <div class="flex justify-between"><span>Add to Cart</span><span>Enter</span></div>
                    <div class="flex justify-between"><span>Checkout</span><span>F9</span></div>
                    <div class="flex justify-between"><span>Clear Cart</span><span>ESC</span></div>
                </div>
            </flux:card>

        </div>
    </div>

    {{-- ============ PAYMENT MODAL ============ --}}
    <flux:modal wire:model="showPaymentModal" class="max-w-md">
        <div class="p-6" x-data x-on:keydown.f6.window.prevent="$wire.setPaymentMethod('cash')"
            x-on:keydown.f7.window.prevent="$wire.setPaymentMethod('card')"
            x-on:keydown.f8.window.prevent="$wire.setPaymentMethod('bank_transfer')">
            <flux:heading class="mb-4">Payment</flux:heading>

            <div class="mb-4 rounded-lg bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                <flux:text class="text-sm text-zinc-500">Total Amount</flux:text>
                <flux:heading size="xl">${{ number_format($this->cartTotal, 2) }}</flux:heading>
            </div>

            {{-- Payment Method --}}
            <flux:field class="mb-4">
                <flux:label>Payment Method</flux:label>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem">
                    <flux:button type="button" :variant="$paymentMethod === 'cash' ? 'primary' : 'outline'"
                        wire:click="setPaymentMethod('cash')">Cash (F6)</flux:button>
                    <flux:button type="button" :variant="$paymentMethod === 'card' ? 'primary' : 'outline'"
                        wire:click="setPaymentMethod('card')">Card (F7)</flux:button>
                    <flux:button type="button" :variant="$paymentMethod === 'bank_transfer' ? 'primary' : 'outline'"
                        wire:click="setPaymentMethod('bank_transfer')">Bank (F8)</flux:button>
                </div>
            </flux:field>

            {{-- Amount Received (Cash only) --}}
            @if ($paymentMethod === 'cash')
                <flux:field class="mb-4">
                    <flux:label>Amount Received</flux:label>
                    <flux:input x-ref="amountInput" wire:model.live="amountReceived" type="number" step="0.01"
                        prefix="৳" autofocus x-on:keydown.enter.prevent="$wire.completeSale()" />
                </flux:field>

                @if ($this->changeDue > 0)
                    <div class="mb-4 rounded-lg bg-green-50 p-3 text-center dark:bg-green-900/20">
                        <flux:text class="text-sm text-zinc-500">Change Due</flux:text>
                        <flux:heading size="lg" class="text-green-600 dark:text-green-400">
                            ${{ number_format($this->changeDue, 2) }}</flux:heading>
                    </div>
                @endif
            @endif

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)" type="button">Cancel
                </flux:button>
                <flux:button wire:click="completeSale" icon="check">Complete Sale</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============ SUCCESS MODAL ============ --}}
    <flux:modal wire:model="showSuccessModal" class="max-w-md">
        <div class="p-6 text-center" x-data x-on:keydown.enter.window="$wire.newSale()">
            <div
                class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                <flux:icon name="check" class="size-8 text-green-600 dark:text-green-400" />
            </div>
            <flux:heading size="lg">Sale Completed!</flux:heading>
            @if ($lastInvoice)
                <flux:text class="mt-2 text-zinc-500">Invoice #{{ $lastInvoice->invoice_number }}</flux:text>
                <flux:heading size="xl" class="mt-2">${{ number_format($lastInvoice->total, 2) }}
                </flux:heading>
            @endif

            <div class="mt-6 flex justify-center gap-3">
                @if ($lastInvoice)
                    <flux:button icon="printer" variant="outline" :href="route('invoices.show', $lastInvoice)"
                        target="_blank">
                        Print Receipt
                    </flux:button>
                @endif
                <flux:button wire:click="newSale" icon="plus">New Sale (Enter)</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

<div class="p-4" x-data="{
    focusSearch() { $refs.searchInput?.focus() },
        focusAmount() { $refs.amountInput?.focus() }
}" x-on:keydown.f2.window.prevent="focusSearch()"
    x-on:keydown.f9.window.prevent="$wire.openPaymentModal()" x-on:keydown.escape.window="$wire.clearCart()">
    {{-- Flash Messages --}}
    @if (session('error'))
        <div class="mb-3 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
            <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
            {{ session('error') }}
        </div>
    @endif

    <div class="grid h-[calc(100vh-6rem)] grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">

        {{-- ============ LEFT: PRODUCT SEARCH & CART ============ --}}
        <div class="flex min-h-0 flex-col gap-4">

            {{-- Search Bar --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative" x-data="{
                    open: true,
                    highlight: 0,
                    get items() { return this.$refs.resultsList ? [...this.$refs.resultsList.querySelectorAll('[data-search-result]')] : [] },
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
                }">
                    <flux:input x-ref="searchInput" wire:model.live.debounce.150ms="search" class="h-12 text-base"
                        placeholder="Scan barcode or search product…" icon="magnifying-glass" autofocus
                        autocomplete="off" name="pos-product-search-nofill" x-on:focus="open = true"
                        x-on:input="open = true; highlight = 0" x-on:keydown.arrow-down.prevent="moveDown()"
                        x-on:keydown.arrow-up.prevent="moveUp()"
                        x-on:keydown.enter.prevent="selectCurrent(); open = false">
                        <x-slot name="iconTrailing">
                            <kbd
                                class="mr-1 rounded border border-zinc-300 bg-zinc-50 px-1.5 py-0.5 text-[0.6875rem] font-medium leading-none text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">F2</kbd>
                        </x-slot>
                    </flux:input>

                    @if ($this->searchResults->count() > 0)
                        <div x-ref="resultsList" x-show="open" x-on:click.outside="open = false"
                            class="absolute z-20 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($this->searchResults as $i => $product)
                                <button type="button" data-search-result wire:click="quickAdd({{ $product->id }})"
                                    x-on:click="open = false" x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="search-{{ $product->id }}"
                                    x-bind:data-active="highlight === {{ $i }}"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors data-[active=true]:bg-zinc-100 dark:data-[active=true]:bg-zinc-800">
                                    @if ($product->media)
                                        <img src="{{ $product->media->file_url }}"
                                            class="size-9 shrink-0 rounded-md object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                    @else
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                                            <flux:icon name="cube" class="size-4 text-zinc-400" />
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ $product->name }}</div>
                                        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                                            <span class="font-mono">{{ $product->sku }}</span>
                                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                            <span
                                                class="{{ $product->quantity > 0 ? '' : 'font-medium text-red-500' }}">{{ $product->quantity > 0 ? $product->quantity . ' in stock' : 'Out of stock' }}</span>
                                        </div>
                                    </div>

                                    <span
                                        class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ money($product->selling_price) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Cart --}}
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

                {{-- Cart header. Fixed h-13 so the Order Summary header on the
                     right column can match it exactly. --}}
                <div
                    class="flex h-13 shrink-0 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <flux:heading size="sm">Current Order</flux:heading>
                        @if (count($cart) > 0)
                            <flux:badge size="sm" color="zinc">{{ count($cart) }}</flux:badge>
                        @endif
                    </div>
                    @if (count($cart) > 0)
                        <flux:button size="xs" variant="subtle" icon="trash" wire:click="clearCart" type="button">
                            Clear
                        </flux:button>
                    @endif
                </div>

                {{-- Cart items --}}
                <div class="min-h-0 flex-1 overflow-y-auto">
                    @if (count($cart) > 0)
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-zinc-200 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-800">
                                    <th class="sticky top-0 bg-zinc-50 px-4 py-2 text-left font-semibold dark:bg-zinc-900">
                                        Product</th>
                                    <th
                                        class="sticky top-0 w-36 bg-zinc-50 px-2 py-2 text-center font-semibold dark:bg-zinc-900">
                                        Qty</th>
                                    <th
                                        class="sticky top-0 w-24 bg-zinc-50 px-2 py-2 text-right font-semibold dark:bg-zinc-900">
                                        Price</th>
                                    <th
                                        class="sticky top-0 w-28 bg-zinc-50 px-2 py-2 text-right font-semibold dark:bg-zinc-900">
                                        Total</th>
                                    <th class="sticky top-0 w-12 bg-zinc-50 dark:bg-zinc-900"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart as $index => $item)
                                    @php
                                        $lineTotal =
                                            $item['price'] * $item['quantity'] - $item['discount'] * $item['quantity'];
                                        $lineTax = $lineTotal * ($item['tax_rate'] / 100);
                                    @endphp
                                    <tr wire:key="cart-{{ $index }}"
                                        class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/30">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}
                                            </div>
                                            <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                            @if (!empty($item['promotion_label']))
                                                <flux:badge size="sm" color="green" icon="tag" class="mt-1.5">
                                                    {{ $item['promotion_label'] }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-2 py-3">
                                            <div
                                                class="mx-auto flex w-fit items-center gap-0.5 rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-700">
                                                <flux:button icon="minus" size="xs" square variant="subtle"
                                                    wire:click="decrementQty({{ $index }})" type="button" />
                                                <input type="number"
                                                    wire:change="updateQty({{ $index }}, $event.target.value)"
                                                    value="{{ $item['quantity'] }}"
                                                    class="w-11 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-zinc-900 focus:outline-none focus:ring-0 dark:text-white [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    min="1" max="{{ $item['max_quantity'] }}" />
                                                <flux:button icon="plus" size="xs" square variant="subtle"
                                                    wire:click="incrementQty({{ $index }})" type="button" />
                                            </div>
                                        </td>
                                        <td class="px-2 py-3 text-right tabular-nums text-zinc-500">
                                            {{ money($item['price']) }}</td>
                                        <td
                                            class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                                            {{ money($lineTotal + $lineTax) }}</td>
                                        <td class="px-2 py-3 text-right">
                                            <flux:button icon="x-mark" size="xs" square variant="subtle"
                                                wire:click="removeFromCart({{ $index }})" type="button" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        {{-- Empty state --}}
                        <div class="flex h-full flex-col items-center justify-center px-6 py-16 text-center">
                            <div
                                class="flex size-14 items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/50">
                                <flux:icon name="shopping-cart" class="size-6 text-zinc-400" />
                            </div>
                            <flux:heading size="sm" class="mt-4">No items yet</flux:heading>
                            <flux:text class="mt-1 max-w-xs text-sm text-zinc-500">
                                Scan a barcode or search for a product to start this order.
                            </flux:text>
                            <flux:button size="sm" variant="subtle" icon="magnifying-glass" class="mt-4" type="button"
                                x-on:click="focusSearch()">
                                Search products
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ============ RIGHT: CUSTOMER & TOTALS ============ --}}
        <div class="flex min-h-0 flex-col gap-4">

            {{-- Customer --}}
            <div class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative" x-data="{ open: true }" x-on:click.outside="open = false">
                    @if ($customer_id)
                        {{-- Selected customer chip. Fixed to h-12 so swapping
                             between the input and this chip never shifts the
                             panel height. --}}
                        <div
                            class="flex h-12 items-center gap-3 rounded-lg border border-zinc-200 px-3 dark:border-zinc-700">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ Str::substr($customerSearch, 0, 2) }}
                            </div>
                            <div class="min-w-0 flex-1 leading-tight">
                                <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $customerSearch }}</div>
                                <div class="text-xs text-zinc-500">Customer</div>
                            </div>
                            <flux:button icon="x-mark" variant="subtle" size="xs" square wire:click="clearCustomer"
                                type="button" />
                        </div>
                    @else
                        {{-- Matches the product search input's height so both
                             column headers align across the terminal. --}}
                        <flux:input wire:model.live.debounce.200ms="customerSearch" class="h-12 text-base"
                            placeholder="Walk-in customer — search name or phone" icon="user"
                            x-on:focus="open = true" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="pos-terminal-search-d89ff9-nofill" />

                        @if ($this->customerResults->count() > 0)
                            <div x-show="open"
                                class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                @foreach ($this->customerResults as $cust)
                                    <button type="button" wire:click="selectCustomer({{ $cust->id }})"
                                        x-on:click="open = false" wire:key="cust-{{ $cust->id }}"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                        <span
                                            class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $cust->name }}</span>
                                        <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $cust->phone }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Totals --}}
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

                {{-- Header row mirroring the cart's, so both columns' content
                     starts on the same baseline. --}}
                <div
                    class="flex h-13 shrink-0 items-center border-b border-zinc-200 px-4 dark:border-zinc-800">
                    <flux:heading size="sm">Order Summary</flux:heading>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">

                    {{-- Adjustments --}}
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Extra Discount</flux:label>
                            <flux:input wire:model.live="discount" type="number" step="0.01" min="0"
                                prefix="৳" class="tabular-nums" />
                        </flux:field>
                        <flux:field>
                            <flux:label class="text-xs">Additional Tax</flux:label>
                            <flux:input wire:model.live="tax" type="number" step="0.01" min="0" prefix="৳"
                                class="tabular-nums" />
                        </flux:field>
                    </div>

                    {{-- Courier. The charge is billed to the customer; the cost is
                         what we pay the courier and stays internal. --}}
                    <div class="mt-3">
                        <flux:checkbox wire:model.live="hasCourier" label="Add courier charge" />

                        @if ($hasCourier)
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <flux:field>
                                    <flux:label class="text-xs">Charge to customer</flux:label>
                                    <flux:input wire:model.live="courierCharge" type="number" step="0.01"
                                        min="0" prefix="৳" class="tabular-nums" />
                                </flux:field>
                                <flux:field>
                                    <flux:label class="text-xs">Courier cost</flux:label>
                                    <flux:input wire:model.live="courierCost" type="number" step="0.01"
                                        min="0" prefix="৳" class="tabular-nums" />
                                </flux:field>
                            </div>

                            @if ($this->courierCostValue > $this->courierChargeValue)
                                <flux:text size="sm" class="mt-2 text-amber-600 dark:text-amber-400">
                                    Shop absorbs {{ money($this->courierCostValue - $this->courierChargeValue) }}
                                </flux:text>
                            @endif
                        @endif
                    </div>

                    {{-- Summary lines --}}
                    <div class="mt-4 space-y-2.5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Items</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ count($cart) }}</span>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Subtotal</span>
                            <span
                                class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->cartSubtotal) }}</span>
                        </div>

                        @if ($this->cartItemDiscountTotal > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-500">Item discounts</span>
                                <span
                                    class="font-medium tabular-nums text-green-600 dark:text-green-400">−{{ money($this->cartItemDiscountTotal) }}</span>
                            </div>
                        @endif

                        @if ($this->cartTaxTotal > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-500">Tax</span>
                                <span
                                    class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->cartTaxTotal) }}</span>
                            </div>
                        @endif

                        @if ($this->courierChargeValue > 0)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-500">Courier</span>
                                <span
                                    class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->courierChargeValue) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Total + actions pinned to the bottom --}}
                <div class="shrink-0 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-baseline justify-between px-4 py-4">
                        <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                        <span
                            class="text-3xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white">{{ money($this->cartTotal) }}</span>
                    </div>

                    <div class="px-4 pb-4">
                        <flux:button wire:click="openPaymentModal" variant="primary" icon="check-circle"
                            class="h-12 w-full text-base" :disabled="count($cart) === 0">
                            <span class="flex w-full items-center justify-center gap-2">
                                Checkout
                                <kbd
                                    class="rounded border border-white/25 px-1.5 py-0.5 text-[0.6875rem] font-medium leading-none dark:border-black/20">F9</kbd>
                            </span>
                        </flux:button>
                    </div>

                    {{-- Shortcuts --}}
                    <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-zinc-500">
                            @foreach (['F2' => 'Search', '↑↓' => 'Navigate', '↵' => 'Add', 'F9' => 'Checkout', 'Esc' => 'Clear'] as $key => $label)
                                <span class="flex items-center gap-1.5">
                                    <kbd
                                        class="inline-flex min-w-6 justify-center rounded border border-zinc-200 bg-zinc-50 px-1 py-0.5 text-[0.6875rem] font-medium leading-none dark:border-zinc-700 dark:bg-zinc-800">{{ $key }}</kbd>
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ============ PAYMENT MODAL ============ --}}
    <flux:modal wire:model="showPaymentModal" class="w-full max-w-md">
        <div x-data x-on:keydown.f6.window.prevent="$wire.setPaymentMethod('cash')"
            x-on:keydown.f7.window.prevent="$wire.setPaymentMethod('card')"
            x-on:keydown.f8.window.prevent="$wire.setPaymentMethod('bank_transfer')">

            {{-- Amount due --}}
            <div class="border-b border-zinc-200 px-6 pb-5 pt-6 text-center dark:border-zinc-800">
                <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Amount Due</flux:text>
                <div class="mt-1.5 text-4xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white">
                    {{ money($this->cartTotal) }}
                </div>
                @if (count($cart) > 0)
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ count($cart) }}
                        {{ Str::plural('item', count($cart)) }}</flux:text>
                @endif
            </div>

            <div class="space-y-5 p-6">
                {{-- Payment Method --}}
                <flux:field>
                    <flux:label class="text-xs font-medium uppercase tracking-wider text-zinc-500">Payment Method
                    </flux:label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ([['cash', 'Cash', 'banknotes', 'F6'], ['card', 'Card', 'credit-card', 'F7'], ['bank_transfer', 'Bank', 'building-library', 'F8']] as [$value, $label, $icon, $key])
                            <button type="button" wire:click="setPaymentMethod('{{ $value }}')"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 transition-colors',
                                    'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' =>
                                        $paymentMethod === $value,
                                    'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' =>
                                        $paymentMethod !== $value,
                                ])>
                                <flux:icon :name="$icon" class="size-5" />
                                <span class="text-xs font-medium">{{ $label }}</span>
                                <span class="text-[0.625rem] opacity-60">{{ $key }}</span>
                            </button>
                        @endforeach
                    </div>
                </flux:field>

                {{-- Amount Received (Cash only) --}}
                @if ($paymentMethod === 'cash')
                    <flux:field>
                        <flux:label class="text-xs font-medium uppercase tracking-wider text-zinc-500">Amount Received
                        </flux:label>
                        <flux:input x-ref="amountInput" wire:model.live="amountReceived" type="number" step="0.01"
                            prefix="৳" autofocus class="h-12 text-lg font-semibold tabular-nums"
                            x-on:keydown.enter.prevent="$wire.completeSale()" />
                    </flux:field>

                    @if ($this->changeDue > 0)
                        <div
                            class="flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900/50 dark:bg-green-950/30">
                            <span class="text-sm font-medium text-green-800 dark:text-green-300">Change Due</span>
                            <span
                                class="text-xl font-semibold tabular-nums text-green-700 dark:text-green-400">{{ money($this->changeDue) }}</span>
                        </div>
                    @endif
                @endif
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)" type="button">Cancel
                </flux:button>
                <flux:button wire:click="completeSale" variant="primary" icon="check">Complete Sale</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ============ SUCCESS MODAL ============ --}}
    <flux:modal wire:model="showSuccessModal" class="w-full max-w-md">
        <div class="px-6 py-8 text-center" x-data x-on:keydown.enter.window="$wire.newSale()">
            <div
                class="mx-auto flex size-14 items-center justify-center rounded-full border border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-950/40">
                <flux:icon name="check" class="size-7 text-green-600 dark:text-green-400" />
            </div>

            <flux:heading size="lg" class="mt-4">Sale Completed</flux:heading>

            @if ($lastInvoice)
                <flux:text class="mt-1 font-mono text-sm text-zinc-500">{{ $lastInvoice->invoice_number }}</flux:text>

                <div class="mt-5 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-zinc-500">Total paid</span>
                        <span
                            class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ money($lastInvoice->total) }}</span>
                    </div>
                    @if ($lastInvoice->payment_method)
                        <div
                            class="flex items-center justify-between border-t border-zinc-200 px-4 py-2.5 dark:border-zinc-800">
                            <span class="text-sm text-zinc-500">Method</span>
                            <span
                                class="text-sm font-medium text-zinc-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $lastInvoice->payment_method)) }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2">
                    <flux:button icon="receipt-percent" variant="outline"
                        :href="route('invoices.receipt', $lastInvoice)" target="_blank">
                        Receipt
                    </flux:button>
                    <flux:button icon="document-text" variant="outline" :href="route('invoices.show', $lastInvoice)"
                        target="_blank">
                        Invoice
                    </flux:button>
                </div>
            @endif

            <flux:button wire:click="newSale" variant="primary" icon="plus" class="mt-2 w-full">
                <span class="flex w-full items-center justify-center gap-2">
                    New Sale
                    <kbd
                        class="rounded border border-white/25 px-1.5 py-0.5 text-[0.6875rem] font-medium leading-none dark:border-black/20">↵</kbd>
                </span>
            </flux:button>
        </div>
    </flux:modal>

</div>

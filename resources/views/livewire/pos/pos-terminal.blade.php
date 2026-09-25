{{-- Client-side cart maths, the pattern from the Livewire docs: quantities and
     adjustments change $wire in the browser without a request, and Alpine
     derives every total by reading $wire. The state reaches the server with the
     next action (checkout, add, remove), where PosTerminal recalculates with
     getCartSubtotalProperty(), getCartItemTaxProperty(), getCartTaxTotalProperty()
     and getCartTotalProperty() — keep these formulas in step with those.
     money() mirrors the PHP money() helper. --}}
<div class="p-4" x-data="{
    currency: @js([
        'symbol' => (string) (App\Models\Setting::get('currency.symbol', '$') ?? '$'),
        'position' => App\Models\Setting::get('currency.position', 'before'),
        'decimals' => (int) App\Models\Setting::get('currency.decimals', 2),
    ]),
    costTouched: @js($hasCourier),
    focusSearch() { $refs.searchInput?.focus() },
    focusAmount() { $refs.amountInput?.focus() },
    num(value) {
        const n = parseFloat(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(n) ? n : 0;
    },
    money(amount) {
        const formatted = this.num(amount).toLocaleString('en-US', {
            minimumFractionDigits: this.currency.decimals,
            maximumFractionDigits: this.currency.decimals,
        });
        return this.currency.position === 'after'
            ? formatted + this.currency.symbol
            : this.currency.symbol + formatted;
    },

    // Quantity is clamped to what the warehouse holds, like PosTerminal::updateQty().
    // live=false keeps the change in the browser until the next action.
    setQty(index, value) {
        const item = this.$wire.cart[index];
        if (! item) return;
        const qty = Math.max(1, Math.min(Math.trunc(this.num(value)) || 1, item.max_quantity));
        this.$wire.$set(`cart.${index}.quantity`, qty, false);
    },
    incrementQty(index) {
        const item = this.$wire.cart[index];
        if (item) this.setQty(index, item.quantity + 1);
    },
    decrementQty(index) {
        const item = this.$wire.cart[index];
        if (! item) return;
        // Dropping below one removes the line, which needs the server to redraw the cart.
        item.quantity > 1 ? this.setQty(index, item.quantity - 1) : this.$wire.removeFromCart(index);
    },

    net(item) {
        return this.num(item?.price) * this.num(item?.quantity) - this.num(item?.discount) * this.num(item?.quantity);
    },
    lineTotal(index) {
        const item = this.$wire.cart[index];
        return this.net(item) * (1 + this.num(item?.tax_rate) / 100);
    },
    get totals() {
        const items = Object.values(this.$wire.cart ?? {});
        const subtotal = items.reduce((sum, item) => sum + this.net(item), 0);
        const itemDiscount = items.reduce((sum, item) => sum + this.num(item.discount) * this.num(item.quantity), 0);
        const tax = items.reduce((sum, item) => sum + this.net(item) * this.num(item.tax_rate) / 100, 0)
            + this.num(this.$wire.tax);
        const courier = this.$wire.hasCourier ? this.num(this.$wire.courierCharge) : 0;
        const courierCost = this.$wire.hasCourier ? this.num(this.$wire.courierCost) : 0;
        return {
            subtotal,
            itemDiscount,
            tax,
            courier,
            courierCost,
            total: Math.max(0, subtotal - this.num(this.$wire.discount) + tax + courier),
        };
    },
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
                <x-search-select model="search" size="lg" autofocus input-ref="searchInput"
                    placeholder="Scan barcode or search product…"
                    :show="$this->searchResults->count() > 0">
                    <x-slot name="trailing">
                        <kbd
                            class="mr-1 rounded border border-zinc-300 bg-zinc-50 px-1.5 py-0.5 text-[0.6875rem] font-medium leading-none text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">F2</kbd>
                    </x-slot>

                    @foreach ($this->searchResults as $i => $product)
                        <x-search-select.option :index="$i" wire:click="quickAdd({{ $product->id }})"
                            wire:key="search-{{ $product->id }}" :label="$product->name"
                            :description="$product->sku . ' · ' . $product->quantity . ' in stock'"
                            :value="money($product->selling_price)">
                            <x-slot name="leading">
                                @if ($product->media)
                                    <img src="{{ $product->media->file_url }}"
                                        class="size-9 shrink-0 rounded-md object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                                @else
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="cube" class="size-4 text-zinc-400" />
                                    </div>
                                @endif
                            </x-slot>
                        </x-search-select.option>
                    @endforeach
                </x-search-select>
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
                                            {{-- Quantity changes stay in the browser ($wire.$set with
                                                 live=false) and are sent with the next action, such as
                                                 checkout. Only dropping below 1 asks the server, since the
                                                 row itself has to go. --}}
                                            <div
                                                class="mx-auto flex w-fit items-center gap-0.5 rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-700">
                                                <flux:button icon="minus" size="xs" square variant="subtle"
                                                    x-on:click="decrementQty({{ $index }})" type="button" />
                                                <input type="number"
                                                    x-bind:value="$wire.cart[{{ $index }}]?.quantity"
                                                    x-on:change="setQty({{ $index }}, $event.target.value); $event.target.value = $wire.cart[{{ $index }}]?.quantity"
                                                    value="{{ $item['quantity'] }}"
                                                    class="w-11 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-zinc-900 focus:outline-none focus:ring-0 dark:text-white [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                    min="1" max="{{ $item['max_quantity'] }}" />
                                                <flux:button icon="plus" size="xs" square variant="subtle"
                                                    x-on:click="incrementQty({{ $index }})" type="button" />
                                            </div>
                                        </td>
                                        <td class="px-2 py-3 text-right tabular-nums text-zinc-500">
                                            {{ money($item['price']) }}</td>
                                        <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white"
                                            x-text="money(lineTotal({{ $index }}))">
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
                {{-- size="lg" matches the product search, so both column headers
                     line up across the terminal. --}}
                <x-search-select model="customerSearch" size="lg" icon="user"
                    placeholder="Walk-in customer — search name or phone"
                    :show="$this->customerResults->count() > 0"
                    :selected="$customer_id ? $customerSearch : null"
                    selected-hint="Customer" clear="clearCustomer">
                    @foreach ($this->customerResults as $i => $cust)
                        <x-search-select.option :index="$i" wire:click="selectCustomer({{ $cust->id }})"
                            wire:key="cust-{{ $cust->id }}" :label="$cust->name" :description="$cust->phone" />
                    @endforeach
                </x-search-select>
            </div>

            {{-- Totals. No min-h-0 here: the card must never be shorter than its
                 content, or the total and checkout spill out below its border
                 when the courier fields are open. flex-1 still stretches it to
                 fill the column when there is room. --}}
            <div
                class="flex flex-1 flex-col rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">

                {{-- Header row mirroring the cart's, so both columns' content
                     starts on the same baseline. --}}
                <div
                    class="flex h-13 shrink-0 items-center border-b border-zinc-200 px-4 dark:border-zinc-800">
                    <flux:heading size="sm">Order Summary</flux:heading>
                </div>

                <div class="flex-1 px-4 py-3">

                    {{-- Every figure below is derived in the browser from $wire (root
                         x-data); inputs are deferred, so typing makes no request. The
                         server recalculates from the same state at checkout. --}}

                    {{-- Adjustments --}}
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Extra Discount</flux:label>
                            <flux:input wire:model="discount" type="number" step="0.01" min="0" size="sm"
                                prefix="৳" class="tabular-nums" />
                        </flux:field>
                        <flux:field>
                            <flux:label class="text-xs">Additional Tax</flux:label>
                            <flux:input wire:model="tax" type="number" step="0.01" min="0" size="sm" prefix="৳"
                                class="tabular-nums" />
                        </flux:field>
                    </div>

                    {{-- Courier. The charge is billed to the customer; the cost is
                         what we pay the courier and stays internal. --}}
                    <div class="mt-2.5">
                        <flux:checkbox wire:model="hasCourier" label="Add courier charge" />

                        <div x-show="$wire.hasCourier" @unless ($hasCourier) style="display: none" @endunless>
                            <div class="mt-2 grid grid-cols-2 gap-3">
                                <flux:field>
                                    <flux:label class="text-xs">Charge to customer</flux:label>
                                    {{-- Mirrors into the cost until the cost is edited by hand. --}}
                                    <flux:input wire:model="courierCharge" type="number" step="0.01" size="sm"
                                        min="0" prefix="৳" class="tabular-nums"
                                        x-on:input="if (! costTouched) $wire.courierCost = $event.target.value" />
                                </flux:field>
                                <flux:field>
                                    <flux:label class="text-xs">Courier cost</flux:label>
                                    <flux:input wire:model="courierCost" type="number" step="0.01" size="sm"
                                        min="0" prefix="৳" class="tabular-nums"
                                        x-on:input="costTouched = true" />
                                </flux:field>
                            </div>

                            {{-- A bound :style, not @unless: Blade directives inside a
                                 component tag break its parser, and the ">" in the
                                 condition ends the tag early. --}}
                            <flux:text size="sm" class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                x-show="totals.courierCost > totals.courier"
                                :style="$this->courierCostValue > $this->courierChargeValue ? '' : 'display: none'">
                                Shop absorbs <span
                                    x-text="money(totals.courierCost - totals.courier)">{{ money(max(0, $this->courierCostValue - $this->courierChargeValue)) }}</span>
                            </flux:text>
                        </div>
                    </div>

                    {{-- Summary lines --}}
                    <div class="mt-3 space-y-1.5 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Items</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ count($cart) }}</span>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Subtotal</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                                x-text="money(totals.subtotal)">{{ money($this->cartSubtotal) }}</span>
                        </div>

                        <div x-show="totals.itemDiscount > 0"
                            @unless ($this->cartItemDiscountTotal > 0) style="display: none" @endunless
                            class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Item discounts</span>
                            <span class="font-medium tabular-nums text-green-600 dark:text-green-400"
                                x-text="'−' + money(totals.itemDiscount)">−{{ money($this->cartItemDiscountTotal) }}</span>
                        </div>

                        <div x-show="totals.tax > 0" @unless ($this->cartTaxTotal > 0) style="display: none" @endunless
                            class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Tax</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                                x-text="money(totals.tax)">{{ money($this->cartTaxTotal) }}</span>
                        </div>

                        <div x-show="totals.courier > 0" @unless ($this->courierChargeValue > 0) style="display: none" @endunless
                            class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Courier</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                                x-text="money(totals.courier)">{{ money($this->courierChargeValue) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Total + actions pinned to the bottom --}}
                <div class="shrink-0 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-baseline justify-between px-4 py-2.5">
                        <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                        <span class="text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white"
                            x-text="money(totals.total)">{{ money($this->cartTotal) }}</span>
                    </div>

                    <div class="px-4 pb-3">
                        <flux:button wire:click="openPaymentModal" variant="primary" icon="check-circle"
                            class="h-11 w-full text-base" :disabled="count($cart) === 0">
                            <span class="flex w-full items-center justify-center gap-2">
                                Checkout
                                <kbd
                                    class="rounded border border-white/25 px-1.5 py-0.5 text-[0.6875rem] font-medium leading-none dark:border-black/20">F9</kbd>
                            </span>
                        </flux:button>
                    </div>

                    {{-- Shortcuts --}}
                    <div class="border-t border-zinc-200 px-4 py-2 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
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

                {{-- Account / card the money goes to (Card & Bank only) --}}
                @if (App\Models\PaymentAccount::requiredFor($paymentMethod))
                    <flux:field>
                        <flux:label class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                            {{ $paymentMethod === 'card' ? 'Card' : 'Account' }}
                        </flux:label>
                        @if ($this->paymentAccounts->isEmpty())
                            <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                                No active {{ $paymentMethod === 'card' ? 'cards' : 'bank accounts' }}.
                                <a href="{{ route('payment-accounts.index') }}" class="underline" wire:navigate>Add one</a> first.
                            </flux:text>
                        @else
                            <flux:select wire:model="paymentAccountId" placeholder="Select account...">
                                @foreach ($this->paymentAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                        <flux:error name="paymentAccountId" />
                    </flux:field>
                @endif

                {{-- Due / credit sale --}}
                <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:text class="text-sm font-medium">Sell on due</flux:text>
                            <flux:text class="text-xs text-zinc-500">Customer pays part now, the rest later</flux:text>
                        </div>
                        <flux:switch wire:model.live="payLater" />
                    </div>

                    @if ($payLater)
                        <div class="mt-3 space-y-3" x-data="{
                            total: @js(round($this->cartTotal, 2)),
                            get due() {
                                const paid = parseFloat(String($wire.paidNow ?? '').replace(/,/g, '')) || 0;
                                return Math.max(0, this.total - paid);
                            },
                        }">
                            @unless ($customer_id)
                                <flux:text class="text-xs text-amber-600 dark:text-amber-400">Select a customer before completing a due sale.</flux:text>
                            @endunless
                            <flux:error name="customer_id" />

                            <flux:field>
                                <flux:label class="text-xs">Paying now</flux:label>
                                <flux:input wire:model="paidNow" type="number" step="0.01" min="0" prefix="৳"
                                    class="tabular-nums" />
                                <flux:error name="paidNow" />
                            </flux:field>

                            <div class="flex items-center justify-between rounded-lg bg-red-50 px-3 py-2 dark:bg-red-950/30">
                                <span class="text-sm font-medium text-red-700 dark:text-red-300">Due</span>
                                <span class="font-semibold tabular-nums text-red-700 dark:text-red-400"
                                    x-text="due.toFixed(2)">{{ number_format($this->dueAfterPayment, 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Amount Received (Cash only) --}}
                @if ($paymentMethod === 'cash' && ! $payLater)
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

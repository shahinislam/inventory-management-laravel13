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
    // live=false keeps the change in the browser until the next action, except
    // for a member sale: then the server re-checks which rewards still apply.
    setQty(index, value) {
        const item = this.$wire.cart[index];
        if (! item || item.is_gift) return;
        // Loose goods (kg, ltr) take a weight to 3 decimals; counted goods stay whole.
        const qty = item.loose
            ? Math.max(0.001, Math.min(Math.round(this.num(value) * 1000) / 1000 || 0.001, item.max_quantity))
            : Math.max(1, Math.min(Math.trunc(this.num(value)) || 1, item.max_quantity));
        this.$wire.$set(`cart.${index}.quantity`, qty, !! this.$wire.customer_id);
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
        const memberDiscount = this.num(this.$wire.membershipDiscount);
        return {
            subtotal,
            itemDiscount,
            memberDiscount,
            tax,
            courier,
            courierCost,
            total: Math.max(0, subtotal - this.num(this.$wire.discount) - memberDiscount + tax + courier),
        };
    },
}" x-on:keydown.f2.window.prevent="focusSearch()"
    x-on:keydown.f4.window.prevent="$wire.openHoldModal()"
    x-on:keydown.f9.window.prevent="$wire.openPaymentModal()"
    x-on:keydown.escape.window="if (! $wire.showRegisterCustomer && ! $wire.showWeighModal && ! $wire.showHoldModal && ! $wire.showHeldList) $wire.clearCart()">
    {{-- Flash Messages --}}
    @if (session('error'))
        <div class="mb-3 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
            <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
            {{ session('error') }}
        </div>
    @endif
    @if (session('success'))
        <div class="mb-3 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300">
            <flux:icon name="check-circle" class="size-4 shrink-0" />
            {{ session('success') }}
        </div>
    @endif

    @php $shift = $this->currentShift; @endphp

    @if (! $shift)
        {{-- ============ NO SHIFT: open one first ============ --}}
        <div class="mx-auto mt-10 max-w-md">
            <flux:card class="p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-950/30">
                        <flux:icon name="banknotes" class="size-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <flux:heading size="lg">Open your shift</flux:heading>
                        <flux:text class="text-sm">Count the cash in the drawer before the first sale.</flux:text>
                    </div>
                </div>

                <form wire:submit="openShift" class="mt-5 space-y-4">
                    <flux:field>
                        <flux:label>Cash in drawer now</flux:label>
                        <flux:input wire:model="openingCash" type="number" step="0.01" min="0" prefix="৳"
                            placeholder="0.00" autofocus class="text-lg tabular-nums" />
                        <flux:description>Enter 0 if the drawer is empty. At closing you count again and the difference is shown.</flux:description>
                        <flux:error name="openingCash" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="lock-open" class="w-full">Open shift &amp; start selling</flux:button>
                </form>
            </flux:card>
        </div>
    @else

    {{-- Shift bar: who is on the till, parked sales, returns, closing. --}}
    <div class="mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-900">
        <flux:badge color="green" size="sm" icon="lock-open">Shift {{ $shift->shift_number }}</flux:badge>
        <span class="text-xs text-zinc-500">since {{ $shift->opened_at->format('h:i A') }}</span>
        <div class="ml-auto flex flex-wrap items-center gap-1.5">
            <flux:button size="xs" variant="subtle" icon="pause" wire:click="openHoldModal" type="button">Hold <kbd class="ml-1 text-[0.625rem] opacity-60">F4</kbd></flux:button>
            <flux:button size="xs" variant="subtle" icon="queue-list" wire:click="$set('showHeldList', true)" type="button">
                Held @if ($this->heldSales->count()) <flux:badge size="sm" color="amber" class="ml-1">{{ $this->heldSales->count() }}</flux:badge> @endif
            </flux:button>
            <flux:button size="xs" variant="subtle" icon="arrow-uturn-left" :href="route('returns.create')" wire:navigate>Return</flux:button>
            <flux:button size="xs" variant="subtle" icon="lock-closed" :href="route('shifts.current')" wire:navigate>Close shift</flux:button>
        </div>
    </div>

    <div class="grid h-[calc(100vh-9rem)] grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">

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
                            :description="$product->sku . ' · ' . format_qty($product->stockIn($warehouse_id), $product->unit) . ' in stock' . ($product->isLoose() ? ' · per ' . $product->unit : '')"
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
                                    @php $isGift = ! empty($item['is_gift']); @endphp
                                    <tr wire:key="cart-{{ $index }}-{{ $isGift ? 'gift' : 'item' }}"
                                        @class([
                                            'border-b border-zinc-100 transition-colors last:border-0 dark:border-zinc-800/70',
                                            'hover:bg-zinc-50 dark:hover:bg-zinc-800/30' => ! $isGift,
                                            'bg-pink-50/60 dark:bg-pink-950/20' => $isGift,
                                        ])>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}
                                            </div>
                                            <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                            @if ($isGift)
                                                <flux:badge size="sm" color="pink" icon="gift" class="mt-1.5">
                                                    {{ $item['promotion_label'] }}</flux:badge>
                                            @elseif (!empty($item['promotion_label']))
                                                <flux:badge size="sm" color="green" icon="tag" class="mt-1.5">
                                                    {{ $item['promotion_label'] }}</flux:badge>
                                            @endif
                                        </td>
                                        @if ($isGift)
                                            <td class="px-2 py-3 text-center text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">
                                                {{ format_qty($item['quantity']) }}</td>
                                            <td class="px-2 py-3 text-right text-xs font-semibold uppercase text-pink-600 dark:text-pink-400">
                                                Free</td>
                                            <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                                                {{ money(0) }}</td>
                                            <td class="px-2 py-3 text-right">
                                                <flux:button icon="x-mark" size="xs" square variant="subtle"
                                                    wire:click="removeFromCart({{ $index }})" type="button"
                                                    title="Take back this gift" />
                                            </td>
                                        @else
                                        <td class="px-2 py-3">
                                            {{-- Quantity changes stay in the browser ($wire.$set with
                                                 live=false) and are sent with the next action, such as
                                                 checkout. Only dropping below 1 asks the server, since the
                                                 row itself has to go. --}}
                                            @if (! empty($item['loose']))
                                                {{-- Weighed item: type the weight, no +/- steps. --}}
                                                <div class="mx-auto flex w-fit items-center gap-1 rounded-lg border border-zinc-200 px-2 py-1 dark:border-zinc-700">
                                                    <input type="number" step="0.001" min="0.001"
                                                        x-bind:value="$wire.cart[{{ $index }}]?.quantity"
                                                        x-on:change="setQty({{ $index }}, $event.target.value); $event.target.value = $wire.cart[{{ $index }}]?.quantity"
                                                        value="{{ $item['quantity'] }}"
                                                        class="w-16 border-0 bg-transparent p-0 text-right text-sm font-semibold tabular-nums text-zinc-900 focus:outline-none focus:ring-0 dark:text-white [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                        max="{{ $item['max_quantity'] }}" />
                                                    <span class="text-xs text-zinc-500">{{ $item['unit'] }}</span>
                                                </div>
                                            @else
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
                                            @endif
                                        </td>
                                        <td class="px-2 py-3 text-right tabular-nums text-zinc-500">
                                            {{ money($item['price']) }}@if (! empty($item['loose']))<span class="text-xs">/{{ $item['unit'] }}</span>@endif</td>
                                        <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white"
                                            x-text="money(lineTotal({{ $index }}))">
                                            {{ money($lineTotal + $lineTax) }}</td>
                                        <td class="px-2 py-3 text-right">
                                            <flux:button icon="x-mark" size="xs" square variant="subtle"
                                                wire:click="removeFromCart({{ $index }})" type="button" />
                                        </td>
                                        @endif
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
                @php $member = $this->selectedCustomer; @endphp
                <x-search-select model="customerSearch" size="lg" icon="user"
                    placeholder="Walk-in customer — search name or phone"
                    :show="$this->customerResults->count() > 0"
                    :selected="$customer_id ? $customerSearch : null"
                    :selected-hint="$member ? 'Member · ' . $member->member_no : 'Customer'" clear="clearCustomer">
                    @foreach ($this->customerResults as $i => $cust)
                        <x-search-select.option :index="$i" wire:click="selectCustomer({{ $cust->id }})"
                            wire:key="cust-{{ $cust->id }}" :label="$cust->name"
                            :description="$cust->phone . ' · ' . $cust->member_no" />
                    @endforeach
                </x-search-select>

                @if (! $customer_id && filled($customerSearch) && $this->customerResults->isEmpty())
                    {{-- Only when a search finds nobody: register on the spot. --}}
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span class="text-zinc-500">No customer found.</span>
                        <flux:button size="xs" variant="primary" icon="user-plus"
                            wire:click="openRegisterCustomer" type="button">
                            Register
                        </flux:button>
                    </div>
                @endif

                @if ($member)
                    @php
                        $eligible = $this->eligibleRewards;
                        $next = $this->nextReward;
                    @endphp
                    <div class="mt-3 space-y-2.5">
                        {{-- Member summary --}}
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-zinc-50 px-2.5 py-1.5 dark:bg-zinc-800/60">
                                <div class="text-zinc-500">Phone</div>
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $member->phone }}</div>
                            </div>
                            <div class="rounded-lg bg-zinc-50 px-2.5 py-1.5 dark:bg-zinc-800/60">
                                <div class="text-zinc-500">Total spent</div>
                                <div class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->memberSpend) }}</div>
                            </div>
                        </div>

                        {{-- Rewards this member qualifies for. The cashier decides. --}}
                        @if ($eligible->isNotEmpty())
                            <div class="rounded-lg border border-pink-200 bg-pink-50/60 p-2.5 dark:border-pink-900/50 dark:bg-pink-950/20">
                                <div class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold text-pink-700 dark:text-pink-300">
                                    <flux:icon name="gift" class="size-4" />
                                    {{ $eligible->count() === 1 ? 'This member earned a reward' : 'This member earned ' . $eligible->count() . ' rewards' }}
                                </div>
                                {{-- Capped so several rewards never push the checkout button off screen. --}}
                                <div class="max-h-52 space-y-1.5 overflow-y-auto">
                                    @foreach ($eligible as $i => $reward)
                                        @php
                                            $applied = $appliedRewards[$reward->id] ?? null;
                                            $problem = $applied ? null : $this->giftProblem($reward);
                                        @endphp
                                        <div wire:key="reward-{{ $reward->id }}"
                                            class="rounded-md bg-white px-2.5 py-2 dark:bg-zinc-900">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 text-sm font-medium text-zinc-900 dark:text-white">
                                                        {{ $reward->reward_label }}
                                                        @if ($i === 0 && $eligible->count() > 1 && ! $applied)
                                                            <flux:badge size="sm" color="pink">Best</flux:badge>
                                                        @endif
                                                    </div>
                                                    <div class="truncate text-xs text-zinc-500">{{ $reward->name }} · {{ $reward->range_label }}</div>
                                                </div>
                                                @if ($applied)
                                                    <flux:button size="xs" variant="subtle" icon="x-mark"
                                                        wire:click="removeReward({{ $reward->id }})" type="button">Undo</flux:button>
                                                @else
                                                    <flux:button size="xs" variant="primary" icon="check"
                                                        wire:click="applyReward({{ $reward->id }})" type="button"
                                                        :disabled="(bool) $problem">Give</flux:button>
                                                @endif
                                            </div>
                                            @if ($problem)
                                                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $problem }}</div>
                                            @endif
                                            @if ($applied && $applied['type'] !== 'gift')
                                                <div class="mt-1.5 flex items-center gap-2">
                                                    <span class="text-xs text-zinc-500">Discount</span>
                                                    <flux:input size="sm" type="number" step="0.01" min="0" prefix="৳"
                                                        class="tabular-nums" wire:model.live.blur="appliedRewards.{{ $reward->id }}.amount" />
                                                </div>
                                            @elseif ($applied)
                                                <div class="mt-1 text-xs font-medium text-green-600 dark:text-green-400">Added to the order as a free item</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- How far to the next milestone — something to tell the customer. --}}
                        @if ($next)
                            <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                                <flux:icon name="sparkles" class="size-3.5 shrink-0 text-amber-500" />
                                <span><span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">{{ money($next['remaining']) }}</span>
                                    more to unlock {{ $next['reward']->reward_label }}</span>
                            </div>
                        @endif
                    </div>
                @endif
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

                        <div x-show="totals.memberDiscount > 0"
                            @unless ($membershipDiscount > 0) style="display: none" @endunless
                            class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Member reward</span>
                            <span class="font-medium tabular-nums text-pink-600 dark:text-pink-400"
                                x-text="'−' + money(totals.memberDiscount)">−{{ money($membershipDiscount) }}</span>
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
                            @foreach (['F2' => 'Search', '↵' => 'Add', 'F4' => 'Hold', 'F9' => 'Checkout', 'Esc' => 'Clear'] as $key => $label)
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
    @endif

    {{-- ============ PAYMENT MODAL ============ --}}
    <flux:modal wire:model="showPaymentModal" class="w-full max-w-md">
        <div x-data x-on:keydown.f6.window.prevent="$wire.setPaymentMethod('cash')"
            x-on:keydown.f7.window.prevent="$wire.setPaymentMethod('card')"
            x-on:keydown.f8.window.prevent="$wire.setPaymentMethod('mobile_banking')"
            x-on:keydown.f10.window.prevent="$wire.setPaymentMethod('bank_transfer')">

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
                    <div class="grid grid-cols-5 gap-2">
                        @foreach ([['cash', 'Cash', 'banknotes', 'F6'], ['card', 'Card', 'credit-card', 'F7'], ['mobile_banking', 'bKash/Nagad', 'device-phone-mobile', 'F8'], ['bank_transfer', 'Bank', 'building-library', 'F10']] as [$value, $label, $icon, $key])
                            <button type="button" wire:click="setPaymentMethod('{{ $value }}')"
                                @class([
                                    'flex flex-col items-center gap-1.5 rounded-xl border px-1 py-3 transition-colors',
                                    'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' =>
                                        ! $splitMode && $paymentMethod === $value,
                                    'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' =>
                                        $splitMode || $paymentMethod !== $value,
                                ])>
                                <flux:icon :name="$icon" class="size-5" />
                                <span class="text-[0.6875rem] font-medium leading-tight">{{ $label }}</span>
                                <span class="text-[0.625rem] opacity-60">{{ $key }}</span>
                            </button>
                        @endforeach
                        <button type="button" wire:click="{{ $splitMode ? 'disableSplit' : 'enableSplit' }}"
                            @class([
                                'flex flex-col items-center gap-1.5 rounded-xl border px-1 py-3 transition-colors',
                                'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $splitMode,
                                'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! $splitMode,
                            ])>
                            <flux:icon name="squares-plus" class="size-5" />
                            <span class="text-[0.6875rem] font-medium leading-tight">Split</span>
                            <span class="text-[0.625rem] opacity-60">2+ ways</span>
                        </button>
                    </div>
                </flux:field>

                @if ($splitMode)
                    {{-- Split: e.g. part cash, part bKash. Parts must add up to the bill. --}}
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        @foreach ($splits as $i => $row)
                            <div wire:key="split-{{ $i }}" class="space-y-1.5 rounded-lg bg-zinc-50 p-2 dark:bg-zinc-800/50">
                                <div class="flex items-center gap-2">
                                    <flux:select wire:model.live="splits.{{ $i }}.method" size="sm" class="w-36">
                                        @foreach (App\Livewire\Pos\PosTerminal::SPLIT_METHODS as $m)
                                            <flux:select.option value="{{ $m }}">{{ App\Models\Payment::methodLabel($m) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:input wire:model.live.debounce.400ms="splits.{{ $i }}.amount" type="number" step="0.01" min="0"
                                        size="sm" prefix="৳" class="flex-1 tabular-nums" placeholder="0.00" />
                                    <flux:button size="xs" variant="subtle" wire:click="fillSplitRemaining({{ $i }})" type="button" title="Fill the rest">Rest</flux:button>
                                    @if (count($splits) > 2)
                                        <flux:button size="xs" variant="subtle" icon="x-mark" square wire:click="removeSplitRow({{ $i }})" type="button" />
                                    @endif
                                </div>
                                @if (App\Models\PaymentAccount::requiredFor($row['method']))
                                    <flux:select wire:model="splits.{{ $i }}.account_id" size="sm" placeholder="Select account...">
                                        @foreach (App\Models\PaymentAccount::forMethod($row['method'])->get() as $account)
                                            <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                                @if (App\Models\Payment::needsReference($row['method']))
                                    <flux:input wire:model="splits.{{ $i }}.reference" size="sm" placeholder="Transaction ID (TrxID)" />
                                @endif
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between pt-1">
                            <flux:button size="xs" variant="subtle" icon="plus" wire:click="addSplitRow" type="button">Add method</flux:button>
                            @php $left = $this->splitRemaining; @endphp
                            <span @class(['text-sm font-semibold tabular-nums', 'text-green-600' => abs($left) < 0.01, 'text-red-600' => abs($left) >= 0.01])>
                                {{ abs($left) < 0.01 ? 'Fully covered' : ($left > 0 ? money($left).' left' : money(-$left).' too much') }}
                            </span>
                        </div>
                        <flux:error name="splits" />
                    </div>
                @endif

                {{-- Account / card the money goes to (Card, bKash/Nagad & Bank) --}}
                @if (! $splitMode && App\Models\PaymentAccount::requiredFor($paymentMethod))
                    <flux:field>
                        <flux:label class="text-xs font-medium uppercase tracking-wider text-zinc-500">
                            {{ match ($paymentMethod) { 'card' => 'Card', 'mobile_banking' => 'Wallet', default => 'Account' } }}
                        </flux:label>
                        @if ($this->paymentAccounts->isEmpty())
                            <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                                No active {{ match ($paymentMethod) { 'card' => 'cards', 'mobile_banking' => 'bKash / Nagad wallets', default => 'bank accounts' } }}.
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

                @if (! $splitMode && App\Models\Payment::needsReference($paymentMethod))
                    <flux:field>
                        <flux:label class="text-xs font-medium uppercase tracking-wider text-zinc-500">Transaction ID</flux:label>
                        <flux:input wire:model="paymentReference" placeholder="e.g. 9BG7KX2LPQ" />
                        <flux:description>From the customer's bKash / Nagad confirmation SMS.</flux:description>
                        <flux:error name="paymentReference" />
                    </flux:field>
                @endif

                {{-- Due / credit sale --}}
                @unless ($splitMode)
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
                @endunless

                {{-- Amount Received (Cash only) --}}
                @if (! $splitMode && $paymentMethod === 'cash' && ! $payLater)
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

    {{-- ============ REGISTER MEMBER MODAL ============ --}}
    <flux:modal wire:model="showRegisterCustomer" class="w-full max-w-sm">
        <form wire:submit="registerCustomer" class="space-y-4 p-6">
            <div>
                <flux:heading size="lg">Register member</flux:heading>
                <flux:text class="mt-1 text-sm">Every customer is a member. The phone number is how they're found next time.</flux:text>
            </div>

            <flux:field>
                <flux:label>Phone <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                <flux:input wire:model="newCustomerPhone" type="tel" placeholder="01XXXXXXXXX" icon="phone" />
                <flux:error name="newCustomerPhone" />
            </flux:field>

            <flux:field>
                <flux:label>Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                <flux:input wire:model="newCustomerName" placeholder="Customer's name" icon="user" />
                <flux:error name="newCustomerName" />
            </flux:field>

            <flux:text class="text-xs text-zinc-500">More details (address, email) can be added later from Customers.</flux:text>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showRegisterCustomer', false)">Cancel</flux:button>
                <flux:button variant="primary" type="submit" icon="check">Register & select</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ============ WEIGH MODAL (loose goods) ============ --}}
    <flux:modal wire:model="showWeighModal" class="w-full max-w-sm">
        @php $weighing = $this->weighProduct; @endphp
        <form wire:submit="confirmWeight" class="space-y-4 p-6">
            <div>
                <flux:heading size="lg">{{ $weighing?->name ?? 'Weigh item' }}</flux:heading>
                @if ($weighing)
                    <flux:text class="mt-1 text-sm">{{ money($weighing->selling_price) }} per {{ $weighing->unit }} · put it on the scale and type the weight.</flux:text>
                @endif
            </div>
            <div x-data="{ price: @js((float) ($weighing?->selling_price ?? 0)) }">
                <flux:field>
                    <flux:label>Weight</flux:label>
                    <flux:input wire:model="weighQuantity" x-ref="weight" type="number" step="0.001" min="0.001"
                        autofocus :suffix="$weighing?->unit" placeholder="e.g. 0.750" class="text-lg tabular-nums" />
                    <flux:error name="weighQuantity" />
                </flux:field>
                <div class="mt-2 flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                    <span class="text-zinc-500">Price</span>
                    <span class="font-semibold tabular-nums" x-text="money(price * num($wire.weighQuantity))"></span>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showWeighModal', false)">Cancel</flux:button>
                <flux:button variant="primary" type="submit" icon="check">Add to order</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ============ HOLD MODAL ============ --}}
    <flux:modal wire:model="showHoldModal" class="w-full max-w-sm">
        <form wire:submit="holdSale" class="space-y-4 p-6">
            <div>
                <flux:heading size="lg">Hold this sale</flux:heading>
                <flux:text class="mt-1 text-sm">Serve the next customer and come back to it from <b>Held</b>. No stock is taken until it's paid.</flux:text>
            </div>
            <flux:field>
                <flux:label>Label (optional)</flux:label>
                <flux:input wire:model="holdLabel" placeholder="e.g. Lady in blue, getting rice" autofocus />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showHoldModal', false)">Cancel</flux:button>
                <flux:button variant="primary" type="submit" icon="pause">Hold sale</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ============ HELD SALES LIST ============ --}}
    <flux:modal wire:model="showHeldList" class="w-full max-w-lg">
        <div class="p-6">
            <flux:heading size="lg">Held sales</flux:heading>
            <flux:text class="mt-1 text-sm">Resuming checks today's prices and stock.</flux:text>
            <div class="mt-4 space-y-2">
                @forelse ($this->heldSales as $held)
                    <div wire:key="held-{{ $held->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $held->held_label }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $held->items_count }} {{ Str::plural('item', $held->items_count) }} · {{ money($held->total) }}
                                · {{ $held->created_at->diffForHumans() }} · {{ $held->createdBy?->name }}
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <flux:button size="xs" variant="primary" icon="play" wire:click="resumeHeld({{ $held->id }})" type="button">Resume</flux:button>
                            <flux:button size="xs" variant="subtle" icon="trash" wire:click="discardHeld({{ $held->id }})"
                                wire:confirm="Discard this held sale?" type="button" />
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-zinc-400">
                        No held sales. Press <kbd class="rounded border px-1">F4</kbd> during a sale to hold it.
                    </div>
                @endforelse
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
                                class="text-sm font-medium text-zinc-900 dark:text-white">{{ App\Models\Payment::methodLabel($lastInvoice->payment_method) }}</span>
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
                    @if (App\Models\Setting::bool('tax.mushak_enabled'))
                        <flux:button icon="document-check" variant="outline" class="col-span-2"
                            :href="route('invoices.mushak', $lastInvoice)" target="_blank">
                            Mushak 6.3 (VAT invoice)
                        </flux:button>
                    @endif
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

{{-- Client-side totals, the pattern from the Livewire docs: inputs use plain
     (deferred) wire:model and Alpine derives every figure by reading $wire, so
     typing costs no server round-trip. The formulas must stay in step with
     InvoiceForm::getSubtotalProperty(), getItemTaxProperty(), getTaxTotalProperty()
     and getTotalProperty(), which the server still uses on save. money() mirrors
     the PHP money() helper. --}}
<div class="p-4" x-data="{
    currency: @js([
        'symbol' => (string) (App\Models\Setting::get('currency.symbol', '$') ?? '$'),
        'position' => App\Models\Setting::get('currency.position', 'before'),
        'decimals' => (int) App\Models\Setting::get('currency.decimals', 2),
    ]),
    costTouched: @js($hasCourier),
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
    net(item) {
        return this.num(item?.quantity) * this.num(item?.unit_price)
            - this.num(item?.discount) * this.num(item?.quantity);
    },
    lineTax(item) {
        return this.net(item) * this.num(item?.tax_rate) / 100;
    },
    lineTotal(index) {
        const item = this.$wire.items[index];
        return this.net(item) + this.lineTax(item);
    },
    get totals() {
        const items = Object.values(this.$wire.items ?? {});
        const subtotal = items.reduce((sum, item) => sum + this.net(item), 0);
        const tax = items.reduce((sum, item) => sum + this.lineTax(item), 0) + this.num(this.$wire.tax);
        const courier = this.$wire.hasCourier ? this.num(this.$wire.courierCharge) : 0;
        const courierCost = this.$wire.hasCourier ? this.num(this.$wire.courierCost) : 0;
        return {
            subtotal,
            tax,
            courier,
            courierCost,
            total: Math.max(0, subtotal - this.num(this.$wire.discount) + tax + courier),
        };
    },
}">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('invoices.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->invoice?->exists ? 'Edit Invoice' : 'New Invoice' }}</flux:heading>
            <flux:text class="mt-1">
                {{ $this->invoice?->exists ? $this->invoice->invoice_number : 'Create a manual invoice' }}</flux:text>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">

        {{-- Left Column --}}
        <div class="min-w-0 space-y-6">

            {{-- Customer --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Customer</flux:heading>

                {{-- Customer Search --}}
                <x-search-select model="customerSearch" icon="user" class="mb-4"
                    placeholder="Search existing customer..."
                    :show="$this->customerResults->count() > 0"
                    :selected="$customer_id ? $customerSearch : null"
                    selected-hint="Customer" clear="clearCustomer">
                    @foreach ($this->customerResults as $i => $cust)
                        <x-search-select.option :index="$i" wire:click="selectCustomer({{ $cust->id }})"
                            wire:key="cust-{{ $cust->id }}" :label="$cust->name" :description="$cust->phone" />
                    @endforeach
                </x-search-select>

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
                <x-search-select model="productSearch" class="mb-4"
                    placeholder="Search product by name, SKU or barcode..."
                    :show="$this->productResults->count() > 0">
                    @foreach ($this->productResults as $i => $product)
                        <x-search-select.option :index="$i" wire:click="addProduct({{ $product->id }})"
                            wire:key="presult-{{ $product->id }}" :label="$product->name"
                            :description="$product->sku . ' · ' . ($this->stockLevels[$product->id] ?? 0) . ' in stock'"
                            :value="money($product->selling_price)" />
                    @endforeach
                </x-search-select>

                {{-- Stock shortfall for the selected warehouse, computed in the browser
                     from the typed quantities. The wire:key changes whenever the
                     server's stock levels do (a product added, warehouse switched),
                     which rebuilds this node so Alpine reads the fresh levels.
                     Drafts may still be saved; saveAsPaid() re-checks on the server. --}}
                @unless ($invoice?->exists && $invoice->status === 'paid')
                    <div wire:key="stock-alert-{{ md5(json_encode($this->stockLevels)) }}"
                        x-data="{
                            stock: @js((object) $this->stockLevels),
                            get shortages() {
                                return Object.values(this.$wire.items ?? {})
                                    .map((item) => ({
                                        name: item.name,
                                        requested: this.num(item.quantity),
                                        available: this.stock[item.product_id] ?? 0,
                                    }))
                                    .filter((s) => s.requested > s.available);
                            },
                        }"
                        x-show="shortages.length > 0"
                        @if ($this->stockShortages === []) style="display: none" @endif
                        class="mb-4 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                        <flux:icon name="exclamation-triangle" class="mt-0.5 size-4 shrink-0" />
                        <div class="min-w-0">
                            <div class="font-medium">Not enough stock in this warehouse</div>
                            <ul class="mt-1 space-y-0.5">
                                <template x-for="s in shortages" :key="s.name">
                                    <li>
                                        <span x-text="s.name"></span> — <span x-text="s.requested"></span> requested,
                                        only <span x-text="s.available"></span> available
                                    </li>
                                </template>
                            </ul>
                            @error('stock')
                                <div class="mt-1.5 font-medium">{{ $message }}</div>
                            @else
                                <div class="mt-1.5 text-xs opacity-80">You can save a draft, but it can't be marked paid
                                    until the stock is available.</div>
                            @enderror
                        </div>
                    </div>
                @endunless

                {{-- Items Table. Five numeric inputs can't compress far, so the
                     table scrolls horizontally rather than crushing columns. --}}
                <div class="-mx-2 overflow-x-auto">
                    <table class="w-full min-w-xl text-sm">
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
                                    $available = $this->stockLevels[$item['product_id']] ?? 0;
                                    $isShort = isset($this->stockShortages[$item['product_id']]);
                                @endphp
                                {{-- Keyed by product and its stock level: removing a row can't hand
                                     another product's Alpine state to this one, and switching
                                     warehouse rebuilds the row with the new level. --}}
                                <tr wire:key="item-{{ $item['product_id'] }}-{{ $available }}"
                                    class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/30">
                                    <td class="px-2 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</div>
                                        <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                        {{-- Stock already left the warehouse on a paid invoice, so the
                                             live count would mislead there. --}}
                                        @unless ($invoice?->exists && $invoice->status === 'paid')
                                            <div class="mt-0.5 text-xs" x-data="{ available: {{ $available }} }">
                                                <div x-show="num($wire.items[{{ $index }}]?.quantity) > available"
                                                    @unless ($isShort) style="display: none" @endunless
                                                    class="flex items-center gap-1 font-medium text-red-600 dark:text-red-400">
                                                    <flux:icon name="exclamation-triangle" class="size-3.5" />
                                                    Only {{ $available }} in stock
                                                </div>
                                                <div x-show="num($wire.items[{{ $index }}]?.quantity) <= available"
                                                    @if ($isShort) style="display: none" @endif
                                                    class="text-zinc-500">
                                                    {{ $available }} in stock
                                                </div>
                                            </div>
                                        @endunless
                                        @if (!empty($item['promotion_label']))
                                            <flux:badge size="sm" color="green" icon="tag" class="mt-1.5">
                                                {{ $item['promotion_label'] }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model="items.{{ $index }}.quantity" type="number"
                                            min="1" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model="items.{{ $index }}.unit_price" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model="items.{{ $index }}.discount" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3">
                                        <flux:input wire:model="items.{{ $index }}.tax_rate" type="number"
                                            step="0.01" size="sm" class="text-right tabular-nums" />
                                    </td>
                                    <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white"
                                        x-text="money(lineTotal({{ $index }}))">
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
        <div class="space-y-4 lg:self-start">

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
                        {{-- Live on purpose: switching warehouse must fetch that
                             warehouse's stock levels from the server. --}}
                        <flux:select wire:model.live="warehouse_id">
                            <flux:select.option value="">No Warehouse</flux:select.option>
                            @foreach ($warehouses as $w)
                                <flux:select.option value="{{ $w->id }}">{{ $w->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model.live="payment_method">
                            <flux:select.option value="cash">Cash</flux:select.option>
                            <flux:select.option value="card">Card</flux:select.option>
                            <flux:select.option value="bank_transfer">Bank Transfer</flux:select.option>
                        </flux:select>
                    </flux:field>

                    @if (App\Models\PaymentAccount::requiredFor($payment_method))
                        <flux:field>
                            <flux:label>{{ $payment_method === 'card' ? 'Card' : 'Account' }}</flux:label>
                            <flux:select wire:model="payment_account_id" placeholder="Select account...">
                                @foreach ($paymentAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:description>Required when marking the invoice paid.</flux:description>
                            <flux:error name="payment_account_id" />
                        </flux:field>
                    @endif
                </div>
            </flux:card>

            {{-- Summary. Every figure is derived in the browser from $wire (root
                 x-data); inputs are deferred, so typing makes no request. The
                 server recalculates from the same inputs on save. --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Summary</flux:heading>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Subtotal</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                            x-text="money(totals.subtotal)">{{ money($this->subtotal) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Discount</flux:label>
                            <flux:input wire:model="discount" type="number" step="0.01" min="0"
                                prefix="৳" class="tabular-nums" />
                        </flux:field>
                        <flux:field>
                            <flux:label class="text-xs">Additional Tax</flux:label>
                            <flux:input wire:model="tax" type="number" step="0.01" min="0" prefix="৳"
                                class="tabular-nums" />
                        </flux:field>
                    </div>

                    {{-- Courier. The charge is billed to the customer; the cost is
                         what we pay the courier and stays internal. --}}
                    <div>
                        <flux:checkbox wire:model="hasCourier" label="Add courier charge" />

                        <div x-show="$wire.hasCourier" @unless ($hasCourier) style="display: none" @endunless>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <flux:field>
                                    <flux:label class="text-xs">Charge to customer</flux:label>
                                    {{-- Mirrors into the cost until the cost is edited by hand. --}}
                                    <flux:input wire:model="courierCharge" type="number" step="0.01"
                                        min="0" prefix="৳" class="tabular-nums"
                                        x-on:input="if (! costTouched) $wire.courierCost = $event.target.value" />
                                </flux:field>
                                <flux:field>
                                    <flux:label class="text-xs">Courier cost</flux:label>
                                    <flux:input wire:model="courierCost" type="number" step="0.01"
                                        min="0" prefix="৳" class="tabular-nums"
                                        x-on:input="costTouched = true" />
                                </flux:field>
                            </div>

                            {{-- A bound :style, not @unless: Blade directives inside a
                                 component tag break its parser, and the ">" in the
                                 condition ends the tag early. --}}
                            <flux:text size="sm" class="mt-2 text-amber-600 dark:text-amber-400"
                                x-show="totals.courierCost > totals.courier"
                                :style="$this->courierCostValue > $this->courierChargeValue ? '' : 'display: none'">
                                Shop absorbs <span
                                    x-text="money(totals.courierCost - totals.courier)">{{ money(max(0, $this->courierCostValue - $this->courierChargeValue)) }}</span>
                            </flux:text>
                        </div>
                    </div>

                    <div x-show="totals.tax > 0" @unless ($this->taxTotal > 0) style="display: none" @endunless
                        class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Tax</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                            x-text="money(totals.tax)">{{ money($this->taxTotal) }}</span>
                    </div>

                    <div x-show="totals.courier > 0" @unless ($this->courierChargeValue > 0) style="display: none" @endunless
                        class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Courier</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                            x-text="money(totals.courier)">{{ money($this->courierChargeValue) }}</span>
                    </div>

                    <div
                        class="flex items-baseline justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                        <span class="text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white"
                            x-text="money(totals.total)">{{ money($this->total) }}</span>
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

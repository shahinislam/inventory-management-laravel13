{{-- Client-side totals, the pattern from the Livewire docs: inputs use plain
     (deferred) wire:model and Alpine derives every figure by reading $wire, so
     typing costs no server round-trip. The formulas must stay in step with
     PurchaseForm::getSubtotalProperty() and getTotalProperty(), which the server
     still uses on save. money() mirrors the PHP money() helper. --}}
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
    lineTotal(index) {
        const item = this.$wire.items[index];
        return this.num(item?.quantity) * this.num(item?.unit_cost);
    },
    get totals() {
        const subtotal = Object.values(this.$wire.items ?? {})
            .reduce((sum, item) => sum + this.num(item.quantity) * this.num(item.unit_cost), 0);
        const courier = this.$wire.hasCourier ? this.num(this.$wire.courierCharge) : 0;
        return {
            subtotal,
            courier,
            total: Math.max(0, subtotal + this.num(this.$wire.tax) - this.num(this.$wire.discount) + courier),
        };
    },
}">

    @php
        $isDraft   = !$order || $order->isDraft();
        $isEditable = $isDraft;
        // "2 carton (=48 pcs)" — the ordered unit, plus base units when they differ.
        $qtyLabel = fn ($qty, $label, $factor, $unit) => format_qty($qty, $label)
            . ((float) $factor > 1 ? ' (=' . format_qty((float) $qty * (float) $factor, $unit) . ')' : '');
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button icon="arrow-left" variant="ghost" href="{{ route('purchases.index') }}" wire:navigate />
            <div>
                <flux:heading size="xl">
                    {{ $order?->exists ? $order->order_number : 'New Purchase Order' }}
                </flux:heading>
                <flux:text class="mt-1">
                    @if($order?->exists)
                        <flux:badge
                            size="sm"
                            :color="match($order->status) {
                                'draft' => 'zinc', 'pending' => 'yellow', 'approved' => 'blue',
                                'ordered' => 'purple', 'received' => 'green', 'cancelled' => 'red', default => 'zinc'
                            }"
                        >{{ ucfirst($order->status) }}</flux:badge>
                    @else
                        Create a new purchase order
                    @endif
                </flux:text>
            </div>
        </div>

        {{-- Workflow Actions --}}
        @if($order?->exists)
            <div class="flex gap-2">
                @if($order->canPlace())
                    <flux:button icon="truck" wire:click="markAsOrdered">Mark as Ordered</flux:button>
                @endif
                @if($order->canRecordPayment())
                    <flux:button icon="banknotes" wire:click="openPaymentModal">Record Payment</flux:button>
                @endif
                @if($order->canReceive())
                    <flux:button variant="primary" icon="inbox-arrow-down" wire:click="openReceiveModal">Receive Stock
                    </flux:button>
                @endif
                @if($this->canReturn)
                    <flux:button icon="arrow-uturn-left" href="{{ route('purchases.return', $order) }}" wire:navigate>Return to supplier</flux:button>
                @endif
                @if(!in_array($order->status, ['received', 'cancelled']))
                    <flux:button icon="x-circle" variant="ghost" wire:click="cancel" wire:confirm="Cancel this purchase order?">Cancel Order</flux:button>
                @endif
            </div>
        @endif
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">

        {{-- Left Column --}}
        <div class="min-w-0 space-y-6">

            {{-- Order Info --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Information</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label badge="Required">Supplier</flux:label>
                        @if($isEditable)
                            <flux:select wire:model="supplier_id" placeholder="Select supplier">
                                <flux:select.option value="">Select supplier</flux:select.option>
                                @foreach($suppliers as $supplier)
                                    <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="supplier_id" />
                        @else
                            <flux:text class="font-medium">{{ $order->supplier->name }}</flux:text>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Warehouse</flux:label>
                        @if($isEditable)
                            <flux:select wire:model="warehouse_id" placeholder="Select warehouse">
                                <flux:select.option value="">No specific warehouse</flux:select.option>
                                @foreach($warehouses as $warehouse)
                                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @else
                            <flux:text class="font-medium">{{ $order->warehouse?->name ?? '-' }}</flux:text>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Required">Order Date</flux:label>
                        @if($isEditable)
                            <flux:input wire:model="order_date" type="date" />
                            <flux:error name="order_date" />
                        @else
                            <flux:text class="font-medium">{{ $order->order_date->format('d M Y') }}</flux:text>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Expected Date</flux:label>
                        @if($isEditable)
                            <flux:input wire:model="expected_date" type="date" />
                            <flux:error name="expected_date" />
                        @else
                            <flux:text class="font-medium">{{ $order->expected_date?->format('d M Y') ?? '-' }}</flux:text>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Payment Due Date</flux:label>
                        @if($isEditable)
                            <flux:input wire:model="payment_due_date" type="date" />
                            <flux:error name="payment_due_date" />
                        @else
                            <flux:text class="font-medium">{{ $order->payment_due_date?->format('d M Y') ?? '-' }}</flux:text>
                        @endif
                    </flux:field>
                </div>
            </flux:card>

            {{-- Items --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Items</flux:heading>

                {{-- Product Search (editable only) --}}
                @if($isEditable)
                <x-search-select model="productSearch" class="mb-4"
                    placeholder="Search product by name, SKU or barcode to add..."
                    :show="$searchResults->count() > 0">
                    @foreach($searchResults as $i => $product)
                        <x-search-select.option :index="$i" wire:click="addProduct({{ $product->id }})"
                            wire:key="search-{{ $product->id }}" :label="$product->name"
                            :description="$product->sku" :value="money($product->cost_price)" />
                    @endforeach
                </x-search-select>
                @endif

                {{-- Items Table --}}
                @php
                    // Column count varies with state, so the empty row's colspan
                    // has to be derived rather than hardcoded.
                    $itemCols = 4 + ($order?->exists ? 1 : 0) + ($isEditable ? 1 : 0);
                @endphp
                <div class="-mx-2 overflow-x-auto">
                    <table class="w-full min-w-lg text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-700">
                                <th class="px-2 py-2.5 text-left font-semibold">Product</th>
                                <th class="w-24 px-2 py-2.5 text-right font-semibold">Qty</th>
                                <th class="w-28 px-2 py-2.5 text-right font-semibold">Unit Cost</th>
                                <th class="w-28 px-2 py-2.5 text-right font-semibold">Subtotal</th>
                                @if($order?->exists)
                                    <th class="w-28 px-2 py-2.5 text-right font-semibold">Received</th>
                                @endif
                                @if($isEditable)
                                    <th class="w-10"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                <tr wire:key="item-{{ $index }}" class="border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50 dark:border-zinc-800/70 dark:hover:bg-zinc-800/30">
                                    <td class="px-2 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $item['name'] }}</div>
                                        <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $item['sku'] }}</div>
                                    </td>
                                    @php
                                        $factor = (float) ($item['unit_factor'] ?? 1);
                                        $label = $item['unit_label'] ?? $item['unit'];
                                        $fraction = !empty($item['loose']) && $factor <= 1;
                                    @endphp
                                    <td class="px-2 py-3 text-right">
                                        @if($isEditable)
                                            <flux:input wire:model="items.{{ $index }}.quantity" type="number"
                                                min="{{ $fraction ? '0.001' : '1' }}" step="{{ $fraction ? '0.001' : '1' }}"
                                                size="sm" class="text-right tabular-nums" />
                                            @if(!empty($item['purchase_unit']))
                                                {{-- Order by the piece or by the carton. --}}
                                                <div class="mt-1 inline-flex overflow-hidden rounded-md border border-zinc-200 text-xs dark:border-zinc-700">
                                                    <button type="button" wire:click="setLineUnit({{ $index }}, 'base')"
                                                        @class(['px-2 py-0.5', 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-900' => $factor <= 1, 'text-zinc-600 dark:text-zinc-300' => $factor > 1])>{{ $item['unit'] }}</button>
                                                    <button type="button" wire:click="setLineUnit({{ $index }}, 'purchase')"
                                                        @class(['px-2 py-0.5', 'bg-zinc-800 text-white dark:bg-white dark:text-zinc-900' => $factor > 1, 'text-zinc-600 dark:text-zinc-300' => $factor <= 1])>{{ $item['purchase_unit'] }} ({{ format_qty($item['purchase_factor']) }})</button>
                                                </div>
                                            @endif
                                            @if($factor > 1)
                                                <div class="mt-0.5 text-xs text-zinc-500">{{ $label }} = {{ format_qty($factor, $item['unit']) }}</div>
                                            @endif
                                            @error("items.$index.quantity") <div class="mt-0.5 text-xs text-red-500">{{ $message }}</div> @enderror
                                        @else
                                            <span class="tabular-nums">{{ $qtyLabel($item['quantity'], $label, $factor, $item['unit']) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-right">
                                        @if($isEditable)
                                            <flux:input wire:model="items.{{ $index }}.unit_cost" type="number" step="0.01" min="0" size="sm" class="text-right tabular-nums" />
                                        @else
                                            <span class="tabular-nums">{{ money($item['unit_cost']) }}</span>
                                        @endif
                                        <div class="mt-0.5 text-xs text-zinc-500">per {{ $label }}</div>
                                    </td>
                                    <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white"
                                        x-text="money(lineTotal({{ $index }}))">
                                        {{ money((float)$item['quantity'] * (float)$item['unit_cost']) }}
                                    </td>
                                    @if($order?->exists)
                                        <td class="px-2 py-3 text-right">
                                            <flux:badge size="sm" :color="(float) $item['received'] >= (float) $item['quantity'] - 0.0005 ? 'green' : ($item['received'] > 0 ? 'yellow' : 'zinc')">
                                                {{ format_qty($item['received']) }} / {{ format_qty($item['quantity']) }}
                                            </flux:badge>
                                        </td>
                                    @endif
                                    @if($isEditable)
                                        <td class="px-2 py-3 text-right">
                                            <flux:button icon="x-mark" variant="subtle" size="xs" square wire:click="removeItem({{ $index }})" type="button" />
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $itemCols }}" class="px-2 py-10 text-center">
                                        <flux:icon name="magnifying-glass" class="mx-auto size-8 text-zinc-300" />
                                        <flux:text class="mt-2 text-sm text-zinc-500">No items added yet. Search above to add products.</flux:text>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('items') <flux:text class="text-sm text-red-500 mt-2">{{ $message }}</flux:text> @enderror
            </flux:card>

            {{-- Notes --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Notes</flux:heading>
                @if($isEditable)
                    <flux:textarea wire:model="notes" placeholder="Additional notes..." rows="3" />
                @else
                    <flux:text class="text-sm text-zinc-500">{{ $order->notes ?? 'No notes' }}</flux:text>
                @endif
            </flux:card>

        </div>

        {{-- Right Column. Sticks on desktop so the running total stays visible
             while working through a long item list. --}}
        <div class="space-y-4 lg:self-start">

            {{-- Totals --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Summary</flux:heading>

                {{-- Every figure here is derived in the browser from $wire (root
                     x-data); inputs are deferred, so typing makes no request. The
                     server recalculates from the same inputs on save. --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Subtotal</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                            x-text="money(totals.subtotal)">{{ money($this->subtotal) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Tax Amount</flux:label>
                            @if($isEditable)
                                <flux:input wire:model="tax" type="number" step="0.01" min="0" prefix="৳" class="tabular-nums" />
                            @else
                                <flux:text class="tabular-nums">{{ money($order->tax) }}</flux:text>
                            @endif
                        </flux:field>

                        <flux:field>
                            <flux:label class="text-xs">Discount</flux:label>
                            @if($isEditable)
                                <flux:input wire:model="discount" type="number" step="0.01" min="0" prefix="৳" class="tabular-nums" />
                            @else
                                <flux:text class="tabular-nums">{{ money($order->discount) }}</flux:text>
                            @endif
                        </flux:field>
                    </div>

                    {{-- Freight. The charge is what the supplier bills us; the cost
                         is anything paid separately to a courier. --}}
                    @if($isEditable)
                        <div>
                            <flux:checkbox wire:model="hasCourier" label="Add courier charge" />

                            <div x-show="$wire.hasCourier" @unless ($hasCourier) style="display: none" @endunless
                                class="mt-3 grid grid-cols-2 gap-3">
                                <flux:field>
                                    <flux:label class="text-xs">Billed by supplier</flux:label>
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
                        </div>
                    @elseif($order?->courier_charge > 0 || $order?->courier_cost > 0)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Courier</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($order->courier_charge) }}</span>
                        </div>
                    @endif

                    @if($isEditable)
                        <div x-show="totals.courier > 0"
                            @unless ($this->courierChargeValue > 0) style="display: none" @endunless
                            class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Courier</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white"
                                x-text="money(totals.courier)">{{ money($this->courierChargeValue) }}</span>
                        </div>
                    @endif

                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                            <span class="text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white"
                                x-text="money(totals.total)">{{ money($this->total) }}</span>
                        </div>
                    </div>

                    @if($order?->exists && !$order->isDraft() && $order->status !== 'cancelled')
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Paid</flux:text>
                            <flux:text class="text-green-500">{{ money($order->paid_amount) }}</flux:text>
                        </div>
                        @if($order->returned_amount > 0)
                            <div class="flex justify-between text-sm">
                                <flux:text class="text-zinc-500">Returned</flux:text>
                                <flux:text class="text-amber-600">{{ money($order->returned_amount) }}</flux:text>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Due</flux:text>
                            <flux:text @class(['text-red-500' => $order->due_amount > 0, 'text-zinc-500' => $order->due_amount <= 0])>{{ money($order->due_amount) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Returns to supplier against this order --}}
            @if($order?->exists && $order->returns->isNotEmpty())
            <flux:card class="p-6">
                <flux:heading class="mb-4">Returns</flux:heading>
                <div class="space-y-3">
                    @foreach($order->returns as $return)
                        <a href="{{ route('purchases.returns.show', $return) }}" wire:navigate wire:key="ret-{{ $return->id }}"
                            class="flex items-start justify-between gap-2 text-sm hover:underline">
                            <div class="min-w-0">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $return->order_number }}</div>
                                <div class="text-xs text-zinc-500">{{ $return->order_date->format('d M Y') }}</div>
                            </div>
                            <span class="shrink-0 font-medium tabular-nums">{{ money($return->total) }}</span>
                        </a>
                    @endforeach
                </div>
            </flux:card>
            @endif

            {{-- Payment History --}}
            @if($order?->exists && $order->payments->isNotEmpty())
            <flux:card class="p-6">
                <flux:heading class="mb-4">Payments</flux:heading>
                <div class="space-y-3">
                    @foreach($order->payments as $payment)
                        <div class="flex items-start justify-between gap-2 text-sm" wire:key="pp-{{ $payment->id }}">
                            <div class="min-w-0">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ App\Models\Payment::methodLabel($payment->method) }}
                                </div>
                                <div class="truncate text-xs text-zinc-500">
                                    {{ $payment->payment_date->format('d M Y') }}
                                    @if($payment->paymentAccount) · {{ $payment->paymentAccount->display_name }} @endif
                                    @if($payment->reference) · {{ $payment->reference }} @endif
                                </div>
                            </div>
                            <span class="shrink-0 font-medium tabular-nums">{{ money($payment->amount) }}</span>
                        </div>
                    @endforeach
                </div>
            </flux:card>
            @endif

            {{-- Actions --}}
            @if($isEditable)
            <flux:card class="p-6">
                <div class="space-y-3">
                    <flux:button wire:click="placeOrder" variant="primary" class="w-full" icon="truck">
                        Place Order
                    </flux:button>
                    <flux:button wire:click="saveDraft" variant="outline" class="w-full" icon="document">
                        Save as Draft
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" href="{{ route('purchases.index') }}" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </flux:card>
            @endif

        </div>
    </div>

    {{-- Receive Stock Modal --}}
    @if($order?->exists)
    <flux:modal wire:model="showReceiveModal" class="w-full max-w-2xl">
        <div class="p-6">
            <flux:heading class="mb-4">Receive Stock</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">Enter quantities being received now. This will update product stock levels.</flux:text>

            <div class="space-y-3">
                @foreach($order->items as $item)
                    @php
                        $remaining = $item->remaining_quantity;
                        $rLabel = $item->unit_label ?: $item->product->unit;
                        $rFraction = $item->product->isLoose() && $item->factor <= 1;
                    @endphp
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800" wire:key="recv-{{ $item->id }}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <flux:text class="truncate text-sm font-medium">{{ $item->product->name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">
                                    Ordered: {{ $qtyLabel($item->quantity, $rLabel, $item->factor, $item->product->unit) }}
                                    · Received: {{ format_qty($item->received_quantity) }}
                                    · Remaining: {{ format_qty($remaining, $rLabel) }}
                                </flux:text>
                            </div>
                            <flux:input
                                wire:model="receiveQuantities.{{ $item->id }}"
                                type="number"
                                min="0"
                                step="{{ $rFraction ? '0.001' : '1' }}"
                                max="{{ $remaining }}"
                                class="w-24 shrink-0 text-right tabular-nums"
                                :disabled="$remaining <= 0"
                            />
                        </div>
                        @if($remaining > 0)
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <flux:input wire:model="receiveBatches.{{ $item->id }}" size="sm" placeholder="Batch no. (optional)" />
                                <flux:input wire:model="receiveExpiry.{{ $item->id }}" type="date" size="sm"
                                    :placeholder="$item->product->track_expiry ? 'Expiry (required)' : 'Expiry (optional)'"
                                    :aria-label="$item->product->track_expiry ? 'Expiry date (required)' : 'Expiry date'" />
                            </div>
                            @if($item->product->track_expiry)
                                <flux:text class="mt-1 text-xs text-zinc-500">Expiry date required for this product.</flux:text>
                            @endif
                        @endif
                        @error("receiveQuantities.{$item->id}") <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text> @enderror
                        @error("receiveExpiry.{$item->id}") <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text> @enderror
                        @error("receiveBatches.{$item->id}") <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text> @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showReceiveModal', false)" type="button">Cancel</flux:button>
                <flux:button variant="primary" wire:click="receiveStock" icon="check">Confirm Receive</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Record Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="w-full max-w-md">
        <div class="p-6">
            <flux:heading>Record Payment</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-500">
                Due to {{ $order->supplier->name }}: <span class="font-medium text-red-500">{{ money($order->due_amount) }}</span>
            </flux:text>

            <div class="mt-5 space-y-4">
                <flux:field>
                    <flux:label badge="Required">Amount</flux:label>
                    <flux:input wire:model="payment_amount" type="number" step="0.01" min="0"
                        max="{{ $order->due_amount }}" prefix="৳" class="tabular-nums" />
                    <flux:error name="payment_amount" />
                </flux:field>

                <flux:field>
                    <flux:label>Payment Method</flux:label>
                    <flux:select wire:model.live="payment_method">
                        @foreach(App\Models\Payment::METHODS as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="payment_method" />
                </flux:field>

                @if (App\Models\PaymentAccount::requiredFor($payment_method))
                    <flux:field>
                        <flux:label>{{ match($payment_method) { 'card' => 'Card', 'mobile_banking' => 'Wallet', default => 'Account' } }}</flux:label>
                        <flux:select wire:model="payment_account_id" placeholder="Select account...">
                            @foreach ($paymentAccounts as $account)
                                <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="payment_account_id" />
                    </flux:field>
                @endif

                <flux:field>
                    @if(App\Models\Payment::needsReference($payment_method))
                        <flux:label badge="Required">Transaction ID</flux:label>
                    @else
                        <flux:label>Reference <flux:text class="text-xs text-zinc-400">(optional)</flux:text></flux:label>
                    @endif
                    <flux:input wire:model="payment_reference" placeholder="Transaction ID / cheque number" />
                    <flux:error name="payment_reference" />
                </flux:field>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)" type="button">Cancel</flux:button>
                <flux:button variant="primary" wire:click="recordPayment" icon="check">Record Payment</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

</div>
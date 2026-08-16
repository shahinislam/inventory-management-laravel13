<div class="p-6">

    @php
        $isDraft   = !$order || $order->isDraft();
        $isEditable = $isDraft;
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
                @if($order->status === 'pending')
                    <flux:button icon="check-circle" wire:click="approve">Approve</flux:button>
                @endif
                @if($order->status === 'approved')
                    <flux:button icon="truck" wire:click="markAsOrdered">Mark as Ordered</flux:button>
                @endif
                @if(in_array($order->status, ['approved', 'ordered']))
                    <flux:button variant="primary" icon="inbox-arrow-down" wire:click="openReceiveModal">Receive Stock
                    </flux:button>
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

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
                </div>
            </flux:card>

            {{-- Items --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Items</flux:heading>

                {{-- Product Search (editable only) --}}
                @if($isEditable)
                <div
                    class="relative mb-4"
                    x-data="{
                        open: true,
                        highlight: 0,
                        count: {{ $searchResults->count() }},
                        moveDown() { if (this.count > 0) this.highlight = (this.highlight + 1) % this.count },
                        moveUp() { if (this.count > 0) this.highlight = (this.highlight - 1 + this.count) % this.count },
                    }"
                    x-on:click.outside="open = false"
                    x-effect="count = {{ $searchResults->count() }}; highlight = 0"
                >
                    <flux:input
                        wire:model.live.debounce.150ms="productSearch"
                        placeholder="Search product by name, SKU or barcode to add..."
                        icon="magnifying-glass"
                        x-on:focus="open = true"
                        x-on:keydown.arrow-down.prevent="moveDown()"
                        x-on:keydown.arrow-up.prevent="moveUp()"
                        x-on:keydown.enter.prevent="open = false; $wire.selectHighlighted(highlight)"
                    />
                    @if($searchResults->count() > 0)
                        <div x-show="open" class="absolute z-10 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach($searchResults as $i => $product)
                                <button
                                    type="button"
                                    wire:click="addProduct({{ $product->id }})"
                                    x-on:click="open = false"
                                    x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="search-{{ $product->id }}"
                                    x-bind:data-active="highlight === {{ $i }}"
                                    class="flex w-full items-center justify-between gap-3 rounded-lg px-2 py-2 text-left transition-colors data-[active=true]:bg-zinc-100 dark:data-[active=true]:bg-zinc-800"
                                >
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $product->name }}</div>
                                        <div class="mt-0.5 text-xs text-zinc-500"><span class="font-mono">{{ $product->sku }}</span> · Cost: <span class="tabular-nums">{{ money($product->cost_price) }}</span></div>
                                    </div>
                                    <flux:icon name="plus" class="size-4 shrink-0 text-zinc-400" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Items Table --}}
                @php
                    // Column count varies with state, so the empty row's colspan
                    // has to be derived rather than hardcoded.
                    $itemCols = 4 + ($order?->exists ? 1 : 0) + ($isEditable ? 1 : 0);
                @endphp
                <div class="-mx-2 overflow-x-auto">
                    <table class="w-full min-w-2xl text-sm">
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
                                    <td class="px-2 py-3 text-right">
                                        @if($isEditable)
                                            <flux:input wire:model.live="items.{{ $index }}.quantity" type="number" min="1" size="sm" class="text-right tabular-nums" />
                                        @else
                                            <span class="tabular-nums">{{ $item['quantity'] }}</span> {{ $item['unit'] }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-right">
                                        @if($isEditable)
                                            <flux:input wire:model.live="items.{{ $index }}.unit_cost" type="number" step="0.01" min="0" size="sm" class="text-right tabular-nums" />
                                        @else
                                            <span class="tabular-nums">{{ money($item['unit_cost']) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white">
                                        {{ money((float)$item['quantity'] * (float)$item['unit_cost']) }}
                                    </td>
                                    @if($order?->exists)
                                        <td class="px-2 py-3 text-right">
                                            <flux:badge size="sm" :color="$item['received'] >= $item['quantity'] ? 'green' : ($item['received'] > 0 ? 'yellow' : 'zinc')">
                                                {{ $item['received'] }} / {{ $item['quantity'] }}
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
        <div class="space-y-6 lg:sticky lg:top-6 lg:self-start">

            {{-- Totals --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Summary</flux:heading>

                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Subtotal</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ money($this->subtotal) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label class="text-xs">Tax Amount</flux:label>
                            @if($isEditable)
                                <flux:input wire:model.live="tax" type="number" step="0.01" min="0" prefix="৳" class="tabular-nums" />
                            @else
                                <flux:text class="tabular-nums">{{ money($order->tax) }}</flux:text>
                            @endif
                        </flux:field>

                        <flux:field>
                            <flux:label class="text-xs">Discount</flux:label>
                            @if($isEditable)
                                <flux:input wire:model.live="discount" type="number" step="0.01" min="0" prefix="৳" class="tabular-nums" />
                            @else
                                <flux:text class="tabular-nums">{{ money($order->discount) }}</flux:text>
                            @endif
                        </flux:field>
                    </div>

                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Total</span>
                            <span class="text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white">{{ money($this->total) }}</span>
                        </div>
                    </div>

                    @if($order?->exists && $order->paid_amount > 0)
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Paid</flux:text>
                            <flux:text class="text-green-500">{{ money($order->paid_amount) }}</flux:text>
                        </div>
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Due</flux:text>
                            <flux:text class="text-red-500">{{ money($order->due_amount) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Actions --}}
            @if($isEditable)
            <flux:card class="p-6">
                <div class="space-y-3">
                    <flux:button wire:click="submitForApproval" class="w-full" icon="paper-airplane">
                        Submit for Approval
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
    <flux:modal wire:model="showReceiveModal" class="max-w-lg">
        <div class="p-6">
            <flux:heading class="mb-4">Receive Stock</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">Enter quantities being received now. This will update product stock levels.</flux:text>

            <div class="space-y-3">
                @foreach($order->items as $item)
                    @php $remaining = $item->quantity - $item->received_quantity; @endphp
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <div class="min-w-0 flex-1">
                            <flux:text class="truncate text-sm font-medium">{{ $item->product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">Ordered: {{ $item->quantity }} · Received: {{ $item->received_quantity }} · Remaining: {{ $remaining }}</flux:text>
                        </div>
                        <flux:input
                            wire:model="receiveQuantities.{{ $item->id }}"
                            type="number"
                            min="0"
                            max="{{ $remaining }}"
                            class="w-24 shrink-0 text-right tabular-nums"
                            :disabled="$remaining <= 0"
                        />
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showReceiveModal', false)" type="button">Cancel</flux:button>
                <flux:button variant="primary" wire:click="receiveStock" icon="check">Confirm Receive</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

</div>
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
                    <flux:button icon="inbox-arrow-down" wire:click="openReceiveModal">Receive Stock</flux:button>
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

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

        {{-- Left Column --}}
        <div class="space-y-6">

            {{-- Order Info --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Information</flux:heading>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <flux:field>
                        <flux:label>Supplier <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
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
                        <flux:label>Order Date <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
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
                    style="position:relative"
                    class="mb-4"
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
                        <div x-show="open" class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach($searchResults as $i => $product)
                                <button
                                    type="button"
                                    wire:click="addProduct({{ $product->id }})"
                                    x-on:click="open = false"
                                    x-on:mouseenter="highlight = {{ $i }}"
                                    wire:key="search-{{ $product->id }}"
                                    class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors"
                                    x-bind:style="highlight === {{ $i }} ? 'background-color: rgb(37 99 235 / 0.15)' : ''"
                                >
                                    <div>
                                        <flux:text x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' : ''" class="text-sm font-medium">{{ $product->name }}</flux:text>
                                        <flux:text class="text-xs text-zinc-400">{{ $product->sku }} · Cost: ${{ number_format($product->cost_price, 2) }}</flux:text>
                                    </div>
                                    <flux:icon name="plus" class="size-4 text-zinc-400" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endif

                {{-- Items Table --}}
                <div style="overflow-x:auto">
                    <table style="width:100%;font-size:0.875rem">
                        <thead>
                            <tr style="border-bottom:1px solid var(--color-zinc-200, #e4e4e7)">
                                <th style="text-align:left;padding:0.5rem;font-weight:500">Product</th>
                                <th style="text-align:right;padding:0.5rem;font-weight:500;width:100px">Qty</th>
                                <th style="text-align:right;padding:0.5rem;font-weight:500;width:120px">Unit Cost</th>
                                <th style="text-align:right;padding:0.5rem;font-weight:500;width:120px">Subtotal</th>
                                @if($order?->exists)
                                    <th style="text-align:right;padding:0.5rem;font-weight:500;width:100px">Received</th>
                                @endif
                                @if($isEditable)
                                    <th style="width:40px"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $index => $item)
                                <tr wire:key="item-{{ $index }}" style="border-bottom:1px solid var(--color-zinc-100, #f4f4f5)">
                                    <td style="padding:0.5rem">
                                        <flux:text class="font-medium">{{ $item['name'] }}</flux:text>
                                        <flux:text class="text-xs text-zinc-400">{{ $item['sku'] }}</flux:text>
                                    </td>
                                    <td style="padding:0.5rem;text-align:right">
                                        @if($isEditable)
                                            <flux:input wire:model.live="items.{{ $index }}.quantity" type="number" min="1" size="sm" />
                                        @else
                                            {{ $item['quantity'] }} {{ $item['unit'] }}
                                        @endif
                                    </td>
                                    <td style="padding:0.5rem;text-align:right">
                                        @if($isEditable)
                                            <flux:input wire:model.live="items.{{ $index }}.unit_cost" type="number" step="0.01" min="0" size="sm" />
                                        @else
                                            ${{ number_format($item['unit_cost'], 2) }}
                                        @endif
                                    </td>
                                    <td style="padding:0.5rem;text-align:right">
                                        <flux:text class="font-medium">${{ number_format((float)$item['quantity'] * (float)$item['unit_cost'], 2) }}</flux:text>
                                    </td>
                                    @if($order?->exists)
                                        <td style="padding:0.5rem;text-align:right">
                                            <flux:badge size="sm" :color="$item['received'] >= $item['quantity'] ? 'green' : ($item['received'] > 0 ? 'yellow' : 'zinc')">
                                                {{ $item['received'] }} / {{ $item['quantity'] }}
                                            </flux:badge>
                                        </td>
                                    @endif
                                    @if($isEditable)
                                        <td style="padding:0.5rem;text-align:right">
                                            <flux:button icon="trash" variant="ghost" size="sm" square wire:click="removeItem({{ $index }})" type="button" />
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:2rem;text-align:center;color:var(--color-zinc-400, #a1a1aa)">
                                        No items added yet. Search above to add products.
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

        {{-- Right Column --}}
        <div class="space-y-6">

            {{-- Totals --}}
            <flux:card class="p-6">
                <flux:heading class="mb-4">Order Summary</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Subtotal</flux:text>
                        <flux:text class="font-medium">${{ number_format($this->subtotal, 2) }}</flux:text>
                    </div>

                    <flux:field>
                        <flux:label class="text-sm">Tax Amount</flux:label>
                        @if($isEditable)
                            <flux:input wire:model.live="tax" type="number" step="0.01" min="0" prefix="৳" />
                        @else
                            <flux:text>${{ number_format($order->tax, 2) }}</flux:text>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label class="text-sm">Discount Amount</flux:label>
                        @if($isEditable)
                            <flux:input wire:model.live="discount" type="number" step="0.01" min="0" prefix="৳" />
                        @else
                            <flux:text>${{ number_format($order->discount, 2) }}</flux:text>
                        @endif
                    </flux:field>

                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <div class="flex justify-between">
                            <flux:heading>Total</flux:heading>
                            <flux:heading>${{ number_format($this->total, 2) }}</flux:heading>
                        </div>
                    </div>

                    @if($order?->exists && $order->paid_amount > 0)
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Paid</flux:text>
                            <flux:text class="text-green-500">${{ number_format($order->paid_amount, 2) }}</flux:text>
                        </div>
                        <div class="flex justify-between text-sm">
                            <flux:text class="text-zinc-500">Due</flux:text>
                            <flux:text class="text-red-500">${{ number_format($order->due_amount, 2) }}</flux:text>
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
                        <div class="flex-1">
                            <flux:text class="text-sm font-medium">{{ $item->product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">Ordered: {{ $item->quantity }} · Received: {{ $item->received_quantity }} · Remaining: {{ $remaining }}</flux:text>
                        </div>
                        <flux:input
                            wire:model="receiveQuantities.{{ $item->id }}"
                            type="number"
                            min="0"
                            max="{{ $remaining }}"
                            style="width:90px"
                            :disabled="$remaining <= 0"
                        />
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showReceiveModal', false)" type="button">Cancel</flux:button>
                <flux:button wire:click="receiveStock" icon="check">Confirm Receive</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif

</div>
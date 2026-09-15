<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('stock.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">Stock Adjustment</flux:heading>
            <flux:text class="mt-1">Manually correct inventory quantities</flux:text>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">

        {{-- Left: Form --}}
        <div>
            <form wire:submit="save">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Adjustment Details</flux:heading>

                    <div class="space-y-4">

                        {{-- Product Search --}}
                        <flux:field>
                            <flux:label badge="Required">Product</flux:label>
                            <x-search-select model="search" autofocus
                                placeholder="Search by name, SKU or scan barcode..."
                                :show="$products->count() > 0"
                                :selected="$selectedProduct?->name"
                                :selected-hint="$selectedProduct?->sku"
                                clear="clearProduct">
                                @foreach($products as $i => $product)
                                    <x-search-select.option :index="$i"
                                        wire:click="selectProduct({{ $product->id }})"
                                        wire:key="prod-{{ $product->id }}"
                                        :label="$product->name"
                                        :description="$product->sku"
                                        :value="$product->quantity . ' ' . $product->unit" />
                                @endforeach
                            </x-search-select>
                            <flux:error name="product_id" />
                        </flux:field>

                        {{-- Current Stock Display --}}
                        @if($selectedProduct)
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="text-sm text-zinc-500">Current Stock</flux:text>
                                        <flux:heading size="lg">{{ $selectedProduct->quantity }} {{ $selectedProduct->unit }}</flux:heading>
                                    </div>
                                    <flux:badge :color="$selectedProduct->isLowStock() ? 'red' : 'green'">
                                        Min: {{ $selectedProduct->min_stock_level }}
                                    </flux:badge>
                                </div>
                            </div>
                        @endif

                        {{-- Adjustment Type --}}
                        <flux:field>
                            <flux:label>Adjustment Type</flux:label>
                            <flux:radio.group wire:model.live="adjustment_type" variant="segmented">
                                <flux:radio value="add" label="Add Stock" icon="plus" />
                                <flux:radio value="remove" label="Remove Stock" icon="minus" />
                                <flux:radio value="set" label="Set Exact" icon="pencil" />
                            </flux:radio.group>
                        </flux:field>

                        {{-- Quantity --}}
                        <flux:field>
                            <flux:label>
                                @if($adjustment_type === 'set')
                                    New Quantity
                                @else
                                    Quantity to {{ $adjustment_type === 'add' ? 'Add' : 'Remove' }}
                                @endif
                                <flux:badge color="red" size="sm">Required</flux:badge>
                            </flux:label>
                            <flux:input wire:model="quantity" type="number" min="0" placeholder="0" />
                            <flux:error name="quantity" />
                        </flux:field>

                        {{-- Preview --}}
                        @if($selectedProduct && $quantity !== '')
                            @php
                                $before = $selectedProduct->quantity;
                                $qty = (int) $quantity;
                                $after = match($adjustment_type) {
                                    'add' => $before + $qty,
                                    'remove' => max(0, $before - $qty),
                                    'set' => $qty,
                                };
                            @endphp
                            <div class="flex items-center justify-center gap-3 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <flux:text class="text-lg font-medium">{{ $before }}</flux:text>
                                <flux:icon name="arrow-right" class="size-5 text-zinc-400" />
                                <flux:text class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $after }}</flux:text>
                                <flux:text class="text-sm text-zinc-400">{{ $selectedProduct->unit }}</flux:text>
                            </div>
                        @endif

                        {{-- Reason --}}
                        <flux:field>
                            <flux:label>Reason <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:select wire:model="reason" placeholder="Select reason">
                                <flux:select.option value="">Select reason</flux:select.option>
                                <flux:select.option value="Stock count correction">Stock count correction</flux:select.option>
                                <flux:select.option value="Damaged goods">Damaged goods</flux:select.option>
                                <flux:select.option value="Expired products">Expired products</flux:select.option>
                                <flux:select.option value="Theft / Loss">Theft / Loss</flux:select.option>
                                <flux:select.option value="Found extra stock">Found extra stock</flux:select.option>
                                <flux:select.option value="Initial stock entry">Initial stock entry</flux:select.option>
                                <flux:select.option value="Other">Other</flux:select.option>
                            </flux:select>
                            <flux:error name="reason" />
                        </flux:field>

                        {{-- Notes --}}
                        <flux:field>
                            <flux:label>Additional Notes</flux:label>
                            <flux:textarea wire:model="notes" placeholder="Optional details..." rows="2" />
                            <flux:error name="notes" />
                        </flux:field>

                    </div>
                </flux:card>

                <div class="mt-4 flex gap-3">
                    <flux:button type="submit" icon="check" :disabled="!$selectedProduct">Save Adjustment</flux:button>
                    <flux:button type="button" variant="ghost" href="{{ route('stock.index') }}" wire:navigate>Cancel</flux:button>
                </div>
            </form>
        </div>

        {{-- Right: Warehouse & Info --}}
        <div class="space-y-6">
            <flux:card class="p-6">
                <flux:heading class="mb-4">Warehouse</flux:heading>
                <flux:field>
                    <flux:label>Location</flux:label>
                    <flux:select wire:model="warehouse_id" placeholder="Select warehouse">
                        @foreach($warehouses as $warehouse)
                            <flux:select.option value="{{ $warehouse->id }}">
                                {{ $warehouse->name }} {{ $warehouse->is_default ? '(Default)' : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </flux:card>

            <flux:card class="p-6">
                <flux:heading class="mb-2">ℹ️ About Adjustments</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Use stock adjustments to correct inventory discrepancies from physical counts,
                    damaged goods, theft, or initial stock entry. All adjustments are logged with
                    your name and timestamp for audit purposes.
                </flux:text>
            </flux:card>
        </div>

    </div>

</div>
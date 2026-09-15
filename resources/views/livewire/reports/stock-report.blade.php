<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Report</flux:heading>
            <flux:text class="mt-1">Current inventory valuation & stock levels</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-tile label="Total Products" :value="number_format($summary['total_products'])" icon="cube" />
        <x-stat-tile label="Stock Cost Value" :value="money($summary['total_value'])" icon="banknotes" />
        <x-stat-tile label="Retail Value" :value="money($summary['retail_value'])" tone="green" icon="tag" />
        <x-stat-tile label="Low Stock" :value="$summary['low_stock']" tone="yellow" icon="arrow-trending-down" />
        <x-stat-tile label="Out of Stock" :value="$summary['out_of_stock']" tone="red" icon="x-circle" />
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr]">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search product..." icon="magnifying-glass" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="stock-report-search-1b0a89-nofill" />
            <flux:select wire:model.live="warehouseFilter" placeholder="All Warehouses">
                <flux:select.option value="">All Warehouses</flux:select.option>
                @foreach($warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="stockFilter" placeholder="All Stock">
                <flux:select.option value="">All Stock</flux:select.option>
                <flux:select.option value="ok">In Stock</flux:select.option>
                <flux:select.option value="low">Low Stock</flux:select.option>
                <flux:select.option value="out">Out of Stock</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$products">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDir" wire:click="sort('name')">Product</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'quantity'" :direction="$sortDir" wire:click="sort('quantity')">Qty</flux:table.column>
                <flux:table.column>Min Level</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'cost_price'" :direction="$sortDir" wire:click="sort('cost_price')">Cost Price</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'selling_price'" :direction="$sortDir" wire:click="sort('selling_price')">Sell Price</flux:table.column>
                <flux:table.column>Stock Value</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($products as $product)
                    <flux:table.row wire:key="{{ $product->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">{{ $product->sku }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $product->category?->name ?? '-' }}</flux:text>
                        </flux:table.cell>
                        @php
                            // With a warehouse selected, show what is held there;
                            // otherwise the total across all warehouses.
                            $qty = $warehouseFilter
                                ? (int) $product->warehouses->first()?->pivot->quantity
                                : $product->quantity;
                        @endphp
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $qty }} {{ $product->unit }}</flux:text>
                            @if($warehouseFilter && $product->quantity !== $qty)
                                <flux:text class="text-xs text-zinc-400">{{ $product->quantity }} total</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $product->min_stock_level }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ money($product->cost_price) }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text>{{ money($product->selling_price) }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ money($qty * $product->cost_price) }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->isOutOfStock() ? 'red' : ($product->isLowStock() ? 'yellow' : 'green')">
                                {{ $product->isOutOfStock() ? 'Out of Stock' : ($product->isLowStock() ? 'Low Stock' : 'In Stock') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <flux:text class="text-zinc-400">No products found</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>
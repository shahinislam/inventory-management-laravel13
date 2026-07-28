<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Report</flux:heading>
            <flux:text class="mt-1">Current inventory valuation & stock levels</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Summary Cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem" class="mb-6">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Total Products</flux:text>
            <flux:heading size="lg" class="mt-1">{{ number_format($summary['total_products']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Stock Cost Value</flux:text>
            <flux:heading size="lg" class="mt-1">{{ money($summary['total_value']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Retail Value</flux:text>
            <flux:heading size="lg" class="mt-1 text-green-600">{{ money($summary['retail_value']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Low Stock</flux:text>
            <flux:heading size="lg" class="mt-1 text-yellow-600">{{ $summary['low_stock'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Out of Stock</flux:text>
            <flux:heading size="lg" class="mt-1 text-red-600">{{ $summary['out_of_stock'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:0.75rem">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search product..." icon="magnifying-glass" />
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
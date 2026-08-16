<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Low Stock Alerts</flux:heading>
            <flux:text class="mt-1">Products that need restocking</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-tile label="Out of Stock" :value="$outOfStock" tone="red" icon="exclamation-triangle"
            hint="Requires immediate action" class="border-l-4 border-l-red-500" />
        <x-stat-tile label="Low Stock" :value="$lowStock" tone="yellow" icon="arrow-trending-down"
            hint="Below minimum level" class="border-l-4 border-l-yellow-500" />
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search product..." icon="magnifying-glass" />
            <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="alertType">
                <flux:select.option value="all">All Alerts</flux:select.option>
                <flux:select.option value="out">Out of Stock</flux:select.option>
                <flux:select.option value="low">Low Stock</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$products">
            <flux:table.columns>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Current Stock</flux:table.column>
                <flux:table.column>Min Level</flux:table.column>
                <flux:table.column>Shortage</flux:table.column>
                <flux:table.column>Alert</flux:table.column>
                <flux:table.column class="text-right">Action</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($products as $product)
                    <flux:table.row wire:key="{{ $product->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $product->name }}</flux:text>
                            <flux:text class="font-mono text-xs text-zinc-400">{{ $product->sku }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $product->category?->name ?? '-' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $product->supplier?->name ?? '-' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="tabular-nums {{ $product->quantity === 0 ? 'font-bold text-red-600 dark:text-red-400' : 'font-medium text-yellow-600 dark:text-yellow-400' }}">
                                {{ $product->quantity }} {{ $product->unit }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm tabular-nums">{{ $product->min_stock_level }} {{ $product->unit }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm tabular-nums text-red-600 dark:text-red-400">
                                {{ max(0, $product->min_stock_level - $product->quantity) }} {{ $product->unit }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$product->quantity === 0 ? 'red' : 'yellow'">
                                {{ $product->quantity === 0 ? 'Out of Stock' : 'Low Stock' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button size="sm" variant="outline" icon="plus" href="{{ route('purchases.create') }}" wire:navigate>
                                Reorder
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="check-circle" class="size-10 text-green-400" />
                                <flux:text class="text-zinc-400">All products are well stocked!</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

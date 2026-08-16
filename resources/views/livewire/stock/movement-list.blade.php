<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Stock Movements</flux:heading>
            <flux:text class="mt-1">Track all inventory changes</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="adjustments-horizontal" variant="outline" href="{{ route('stock.adjust') }}" wire:navigate>
                Adjust Stock
            </flux:button>
            <flux:button icon="arrows-right-left" href="{{ route('stock.transfer') }}" wire:navigate>
                Transfer Stock
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by product name or SKU..."
                icon="magnifying-glass"
            />

            <flux:select wire:model.live="typeFilter" placeholder="All Types">
                <flux:select.option value="">All Types</flux:select.option>
                <flux:select.option value="purchase">Purchase</flux:select.option>
                <flux:select.option value="sale">Sale</flux:select.option>
                <flux:select.option value="return">Return</flux:select.option>
                <flux:select.option value="adjustment">Adjustment</flux:select.option>
                <flux:select.option value="transfer_in">Transfer In</flux:select.option>
                <flux:select.option value="transfer_out">Transfer Out</flux:select.option>
                <flux:select.option value="damaged">Damaged</flux:select.option>
                <flux:select.option value="expired">Expired</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="warehouseFilter" placeholder="All Warehouses">
                <flux:select.option value="">All Warehouses</flux:select.option>
                @foreach($warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live="dateFrom" type="date" placeholder="From" />
            <flux:input wire:model.live="dateTo" type="date" placeholder="To" />

            <flux:button icon="x-mark" variant="ghost" wire:click="resetFilters" square />
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$movements">
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Quantity</flux:table.column>
                <flux:table.column>Before → After</flux:table.column>
                <flux:table.column>By</flux:table.column>
                <flux:table.column>Notes</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($movements as $movement)
                    <flux:table.row wire:key="{{ $movement->id }}">

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $movement->created_at->format('d M Y') }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">{{ $movement->created_at->format('h:i A') }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $movement->product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">{{ $movement->product->sku }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $movement->warehouse?->name ?? '-' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="match($movement->type) {
                                    'purchase', 'return', 'transfer_in' => 'green',
                                    'sale', 'transfer_out' => 'blue',
                                    'damaged', 'expired' => 'red',
                                    'adjustment' => 'yellow',
                                    default => 'zinc'
                                }"
                            >{{ ucfirst(str_replace('_', ' ', $movement->type)) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="font-medium {{ $movement->isInbound() ? 'text-green-500' : 'text-red-500' }}">
                                {{ $movement->isInbound() ? '+' : '-' }}{{ $movement->quantity }}
                            </flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $movement->before_quantity }} → {{ $movement->after_quantity }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $movement->createdBy->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm text-zinc-400">{{ $movement->notes ?? '-' }}</flux:text>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="arrows-up-down" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No stock movements found</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

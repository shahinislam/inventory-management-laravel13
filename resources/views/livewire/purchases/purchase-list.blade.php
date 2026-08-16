<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Purchase Orders</flux:heading>
            <flux:text class="mt-1">Manage supplier orders & stock receiving</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('purchases.create') }}" wire:navigate>
            New Purchase Order
        </flux:button>
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

    {{-- Search & Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by order number or supplier..."
                icon="magnifying-glass"
            />
            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="approved">Approved</flux:select.option>
                <flux:select.option value="ordered">Ordered</flux:select.option>
                <flux:select.option value="received">Received</flux:select.option>
                <flux:select.option value="cancelled">Cancelled</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="supplierFilter" placeholder="All Suppliers">
                <flux:select.option value="">All Suppliers</flux:select.option>
                @foreach($suppliers as $supplier)
                    <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$orders">
            <flux:table.columns>
                <flux:table.column>Order #</flux:table.column>
                <flux:table.column>Supplier</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Items</flux:table.column>
                <flux:table.column>Total</flux:table.column>
                <flux:table.column>Order Date</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($orders as $order)
                    <flux:table.row wire:key="{{ $order->id }}">

                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $order->order_number }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">{{ $order->createdBy->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $order->supplier->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $order->warehouse?->name ?? '-' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $order->items_count }} items</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="font-medium">{{ money($order->total) }}</flux:text>
                            @if($order->paid_amount > 0)
                                <flux:text class="text-xs text-green-500">Paid: {{ money($order->paid_amount) }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $order->order_date->format('d M Y') }}</flux:text>
                            @if($order->expected_date)
                                <flux:text class="text-xs text-zinc-400">Expected: {{ $order->expected_date->format('d M Y') }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="match($order->status) {
                                    'draft'     => 'zinc',
                                    'pending'   => 'yellow',
                                    'approved'  => 'blue',
                                    'ordered'   => 'purple',
                                    'received'  => 'green',
                                    'cancelled' => 'red',
                                    default     => 'zinc'
                                }"
                            >{{ ucfirst($order->status) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('purchases.edit', $order)" wire:navigate>
                                        {{ $order->isDraft() ? 'Edit' : 'View' }}
                                    </flux:menu.item>
                                    @if($order->isDraft())
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $order->id }})">Delete</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="clipboard-document-list" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No purchase orders found</flux:text>
                                <flux:button size="sm" href="{{ route('purchases.create') }}" wire:navigate>
                                    Create your first order
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Delete Modal --}}
    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete Purchase Order</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

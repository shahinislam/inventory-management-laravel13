<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Dashboard</flux:heading>
            <flux:text class="mt-1">Welcome back, {{ auth()->user()->name }}</flux:text>
        </div>

        {{-- Period Filter --}}
        <div class="flex items-center gap-2">
            <flux:button
                size="sm"
                :variant="$period === 'today' ? 'primary' : 'ghost'"
                wire:click="setPeriod('today')"
            >Today</flux:button>
            <flux:button
                size="sm"
                :variant="$period === 'week' ? 'primary' : 'ghost'"
                wire:click="setPeriod('week')"
            >This Week</flux:button>
            <flux:button
                size="sm"
                :variant="$period === 'month' ? 'primary' : 'ghost'"
                wire:click="setPeriod('month')"
            >This Month</flux:button>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">

        {{-- Total Sales --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500">Total Sales</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ money($stats['totalSales']) }}</flux:heading>
                    <flux:text class="text-xs text-zinc-400 mt-1">{{ $stats['totalInvoices'] }} invoices</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                    <flux:icon name="currency-dollar" class="size-5 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        {{-- Total Products --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500">Total Products</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ number_format($stats['totalProducts']) }}</flux:heading>
                    @if($stats['lowStock'] > 0)
                        <flux:text class="text-xs text-red-500 mt-1">{{ $stats['lowStock'] }} low stock</flux:text>
                    @else
                        <flux:text class="text-xs text-green-500 mt-1">All stocked</flux:text>
                    @endif
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                    <flux:icon name="cube" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        {{-- Total Customers --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500">Customers</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ number_format($stats['totalCustomers']) }}</flux:heading>
                    <flux:text class="text-xs text-zinc-400 mt-1">Active customers</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                    <flux:icon name="users" class="size-5 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>

        {{-- Pending Orders --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500">Pending Orders</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ number_format($stats['pendingOrders']) }}</flux:heading>
                    <flux:text class="text-xs text-zinc-400 mt-1">Purchase orders</flux:text>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                    <flux:icon name="clipboard-document-list" class="size-5 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>

    </div>

    {{-- Quick Actions --}}
    <div class="mb-6 flex flex-wrap gap-2">
        <flux:button icon="plus" href="{{ route('invoices.create') }}" wire:navigate>New Invoice</flux:button>
        <flux:button icon="shopping-cart" variant="filled" href="{{ route('pos') }}" wire:navigate>POS Terminal</flux:button>
        <flux:button icon="clipboard-document-list" variant="outline" href="{{ route('purchases.create') }}" wire:navigate>New Purchase</flux:button>
        <flux:button icon="exclamation-circle" variant="outline" href="{{ route('reports.low-stock') }}" wire:navigate>Low Stock</flux:button>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Recent Invoices --}}
        <flux:card>
            <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Recent Invoices</flux:heading>
                <flux:button size="sm" variant="ghost" href="{{ route('invoices.index') }}" wire:navigate>View All</flux:button>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($recentInvoices as $invoice)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div>
                            <flux:text class="text-sm font-medium">{{ $invoice->invoice_number }}</flux:text>
                            <flux:text class="text-xs text-zinc-500">{{ $invoice->customer_name }}</flux:text>
                        </div>
                        <div class="text-right">
                            <flux:text class="text-sm font-medium">{{ money($invoice->total) }}</flux:text>
                            <flux:badge
                                size="sm"
                                :color="match($invoice->status) {
                                    'paid'      => 'green',
                                    'partial'   => 'yellow',
                                    'overdue'   => 'red',
                                    'cancelled' => 'zinc',
                                    default     => 'blue'
                                }"
                            >{{ ucfirst($invoice->status) }}</flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <flux:text class="text-zinc-400">No invoices yet</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Low Stock Alerts --}}
        <flux:card>
            <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Low Stock Alerts</flux:heading>
                <flux:button size="sm" variant="ghost" href="{{ route('reports.low-stock') }}" wire:navigate>View All</flux:button>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($lowStockProducts as $product)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div>
                            <flux:text class="text-sm font-medium">{{ $product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-500">{{ $product->sku }}</flux:text>
                        </div>
                        <div class="text-right">
                            <flux:badge size="sm" color="red">
                                {{ $product->quantity }} / {{ $product->min_stock_level }}
                            </flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <flux:text class="text-green-500">All products are well stocked! ✓</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Recent Stock Movements --}}
        <flux:card>
            <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Recent Stock Movements</flux:heading>
                <flux:button size="sm" variant="ghost" href="{{ route('stock.index') }}" wire:navigate>View All</flux:button>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($recentMovements as $movement)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div>
                            <flux:text class="text-sm font-medium">{{ $movement->product->name }}</flux:text>
                            <flux:text class="text-xs text-zinc-500">{{ $movement->createdBy->name }} · {{ $movement->created_at->diffForHumans() }}</flux:text>
                        </div>
                        <div class="text-right">
                            <flux:badge
                                size="sm"
                                :color="$movement->isInbound() ? 'green' : 'red'"
                            >
                                {{ $movement->isInbound() ? '+' : '-' }}{{ $movement->quantity }}
                            </flux:badge>
                            <flux:text class="text-xs text-zinc-500 mt-1">{{ ucfirst(str_replace('_', ' ', $movement->type)) }}</flux:text>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <flux:text class="text-zinc-400">No stock movements yet</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Sales Chart (Last 7 Days) --}}
        <flux:card>
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Sales Last 7 Days</flux:heading>
            </div>
            <div class="p-4">
                <div class="flex items-end justify-between gap-2 h-32">
                    @php $maxSales = max(array_column($salesChart, 'sales')) ?: 1; @endphp
                    @foreach($salesChart as $day)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <flux:text class="text-xs text-zinc-500">{{ money($day['sales']) }}</flux:text>
                            <div
                                class="w-full rounded-t bg-blue-500 dark:bg-blue-400 min-h-1"
                                style="height: {{ max(4, ($day['sales'] / $maxSales) * 80) }}px"
                            ></div>
                            <flux:text class="text-xs text-zinc-500">{{ $day['date'] }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:card>

    </div>

</div>

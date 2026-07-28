<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Sales Report</flux:heading>
            <flux:text class="mt-1">Revenue & sales analytics</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Period Filter --}}
    <flux:card class="mb-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="period" style="width:160px">
                <flux:select.option value="today">Today</flux:select.option>
                <flux:select.option value="yesterday">Yesterday</flux:select.option>
                <flux:select.option value="this_week">This Week</flux:select.option>
                <flux:select.option value="this_month">This Month</flux:select.option>
                <flux:select.option value="last_month">Last Month</flux:select.option>
                <flux:select.option value="this_year">This Year</flux:select.option>
                <flux:select.option value="custom">Custom Range</flux:select.option>
            </flux:select>

            @if($period === 'custom')
                <flux:input wire:model.live="dateFrom" type="date" />
                <flux:text>to</flux:text>
                <flux:input wire:model.live="dateTo" type="date" />
            @endif
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem" class="mb-6">
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Total Revenue</flux:text>
            <flux:heading size="lg" class="mt-1 text-green-600">{{ money($summary['total_sales']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Total Invoices</flux:text>
            <flux:heading size="lg" class="mt-1">{{ $summary['total_invoices'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Total Paid</flux:text>
            <flux:heading size="lg" class="mt-1 text-blue-600">{{ money($summary['total_paid']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Total Due</flux:text>
            <flux:heading size="lg" class="mt-1 text-red-600">{{ money($summary['total_due']) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-xs text-zinc-500">Avg Invoice</flux:text>
            <flux:heading size="lg" class="mt-1">{{ money($summary['avg_invoice']) }}</flux:heading>
        </flux:card>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

        {{-- Daily Breakdown --}}
        <flux:card>
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Daily Breakdown</flux:heading>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Invoices</flux:table.column>
                    <flux:table.column>Revenue</flux:table.column>
                    <flux:table.column>Paid</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($dailySales as $day)
                        <flux:table.row wire:key="{{ $day->date }}">
                            <flux:table.cell>{{ \Carbon\Carbon::parse($day->date)->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ $day->count }}</flux:badge></flux:table.cell>
                            <flux:table.cell><flux:text class="font-medium">{{ money($day->total) }}</flux:text></flux:table.cell>
                            <flux:table.cell><flux:text class="text-green-500">{{ money($day->paid) }}</flux:text></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-8 text-center">
                                <flux:text class="text-zinc-400">No sales in this period</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{-- Top Products --}}
        <flux:card>
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading>Top Products</flux:heading>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Product</flux:table.column>
                    <flux:table.column>Qty Sold</flux:table.column>
                    <flux:table.column>Revenue</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($topProducts as $product)
                        <flux:table.row wire:key="{{ $product->product_sku }}">
                            <flux:table.cell>
                                <flux:text class="font-medium">{{ $product->product_name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $product->product_sku }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ $product->total_qty }}</flux:badge></flux:table.cell>
                            <flux:table.cell><flux:text class="font-medium">{{ money($product->total_revenue) }}</flux:text></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="py-8 text-center">
                                <flux:text class="text-zinc-400">No data</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

    </div>

</div>
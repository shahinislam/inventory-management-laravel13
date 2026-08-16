<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Sales Report</flux:heading>
            <flux:text class="mt-1">Revenue & sales analytics</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Period Filter --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="period" class="w-40">
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
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-tile label="Total Revenue" :value="money($summary['total_sales'])" tone="green" icon="banknotes" />
        <x-stat-tile label="Total Invoices" :value="$summary['total_invoices']" icon="document-text" />
        <x-stat-tile label="Total Paid" :value="money($summary['total_paid'])" tone="blue" icon="check-circle" />
        <x-stat-tile label="Total Due" :value="money($summary['total_due'])" tone="red" icon="clock" />
        <x-stat-tile label="Avg Invoice" :value="money($summary['avg_invoice'])" icon="calculator" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

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
                            <flux:table.cell><flux:text class="font-medium tabular-nums">{{ money($day->total) }}</flux:text></flux:table.cell>
                            <flux:table.cell><flux:text class="tabular-nums text-green-600 dark:text-green-400">{{ money($day->paid) }}</flux:text></flux:table.cell>
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
                                <flux:text class="font-mono text-xs text-zinc-400">{{ $product->product_sku }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" color="zinc">{{ $product->total_qty }}</flux:badge></flux:table.cell>
                            <flux:table.cell><flux:text class="font-medium tabular-nums">{{ money($product->total_revenue) }}</flux:text></flux:table.cell>
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
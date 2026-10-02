<div class="p-4" data-print-report="Profit &amp; Loss">

    @php
        $change = fn (float $now, float $before) => \App\Livewire\Reports\ProfitLossReport::change($now, $before);

        // Statement lines: [label, key, sign, helper, emphasis]
        $lines = [
            ['Sales of goods', 'goods_sales', '+', 'What customers were charged for items, after item discounts. Excludes VAT and courier.', false],
            ['Bill discounts', 'discount', '−', 'Discounts given on the whole bill.', false],
            ['Member reward discounts', 'membership_discount', '−', 'Rewards given to members at checkout.', false],
            ['Returns', 'returns', '−', 'Value of goods customers brought back (refunded or credited).', false],
            ['Net sales', 'net_sales', '=', 'What you actually earned from selling goods.', true],
            ['Cost of goods sold', 'cogs', '−', 'What the items you sold cost you to buy. Restocked returns are taken off; damaged returns stay as a loss.', false],
            ['Gross profit', 'gross_profit', '=', 'Profit from selling, before running costs.', true],
            ['Courier margin', 'courier_margin', '±', 'Delivery charged to customers minus what the courier cost you.', false],
            ['Expenses', 'expense_total', '−', 'Rent, salaries, bills and other running costs recorded under Expenses.', false],
        ];

        $tone = fn (float $v) => $v < 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white';
    @endphp

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Profit &amp; Loss</flux:heading>
            <flux:text class="mt-1">Did the shop make money? Sales, costs and expenses for {{ $rangeLabel }}</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Period Filter --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="period" class="w-40">
                @foreach($this->periodOptions() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($period === 'custom')
                <flux:input wire:model.live="dateFrom" type="date" class="w-40" />
                <flux:text>to</flux:text>
                <flux:input wire:model.live="dateTo" type="date" class="w-40" />
            @endif

            <flux:text class="text-sm text-zinc-500">Compared with {{ $previousLabel }}</flux:text>
        </div>
    </flux:card>

    {{-- Headline --}}
    @php
        $profit = $current['net_profit'];
        $profitChange = $change($profit, $previous['net_profit']);
    @endphp
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div @class([
            'rounded-2xl border p-6 shadow-sm',
            'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $profit >= 0,
            'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10' => $profit < 0,
        ])>
            <div class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ $profit >= 0 ? 'Net profit' : 'Net loss' }}
            </div>
            <div @class([
                'mt-2 text-3xl font-bold tabular-nums sm:text-4xl',
                'text-emerald-700 dark:text-emerald-300' => $profit >= 0,
                'text-rose-700 dark:text-rose-300' => $profit < 0,
            ])>{{ money(abs($profit)) }}</div>
            <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                @if($profit >= 0)
                    The shop earned more than it spent.
                @else
                    The shop spent more than it earned.
                @endif
                @if($current['net_margin'] !== null)
                    That is {{ $current['net_margin'] }}% of net sales.
                @endif
            </div>
            @if($profitChange !== null)
                <div class="mt-2 text-xs {{ $profitChange >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                    {{ $profitChange >= 0 ? '▲' : '▼' }} {{ abs($profitChange) }}% vs previous period ({{ money($previous['net_profit']) }})
                </div>
            @endif
        </div>

        <x-stat-tile label="Net sales" :value="money($current['net_sales'])" tone="blue" icon="banknotes" :hint="$current['invoices'].' sale'.($current['invoices'] === 1 ? '' : 's').', excl. VAT'" />
        <x-stat-tile label="Gross profit" :value="money($current['gross_profit'])" tone="green" icon="arrow-trending-up" :hint="$current['gross_margin'] !== null ? $current['gross_margin'].'% margin' : 'No sales yet'" />
        <x-stat-tile label="Expenses" :value="money($current['expense_total'])" tone="red" icon="receipt-percent" :hint="count($current['expenses']).' categor'.(count($current['expenses']) === 1 ? 'y' : 'ies')" />
    </div>

    @if($current['unknown_cost_lines'] > 0)
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-6">
            <flux:callout.heading>Cost unknown for {{ $current['unknown_cost_lines'] }} sold line{{ $current['unknown_cost_lines'] === 1 ? '' : 's' }}</flux:callout.heading>
            <flux:callout.text>
                {{ money($current['unknown_cost_sales']) }} of sales had no purchase cost recorded, so they count as pure profit here.
                Set a cost price on those products (or receive them through a purchase order) to make profit accurate.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.5fr_1fr]">

        {{-- Statement --}}
        <flux:card>
            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>Statement</flux:heading>
                <flux:text class="text-xs text-zinc-500">Read top to bottom: what came in, what it cost, what is left.</flux:text>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-4 py-2 font-medium"></th>
                            <th class="px-4 py-2 text-right font-medium">This period</th>
                            <th class="px-4 py-2 text-right font-medium">Previous</th>
                            <th class="px-4 py-2 text-right font-medium">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as [$label, $key, $sign, $helper, $strong])
                            @php
                                $now = (float) $current[$key];
                                $before = (float) $previous[$key];
                                $pct = $change($now, $before);
                                // For costs, going up is bad.
                                $costLine = $sign === '−';
                                $good = $pct === null ? null : ($costLine ? $pct <= 0 : $pct >= 0);
                            @endphp
                            <tr @class([
                                'border-b border-zinc-100 dark:border-zinc-800',
                                'bg-zinc-50 font-semibold dark:bg-zinc-800/50' => $strong,
                            ])>
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-2">
                                        <span class="w-4 shrink-0 text-center font-mono text-zinc-400">{{ $sign }}</span>
                                        <div>
                                            <div class="text-zinc-900 dark:text-white">{{ $label }}</div>
                                            <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $helper }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $tone($now) }}">{{ money($now) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-zinc-500">{{ money($before) }}</td>
                                <td class="px-4 py-3 text-right text-xs tabular-nums">
                                    @if($pct === null)
                                        <span class="text-zinc-400">—</span>
                                    @else
                                        <span class="{{ $good ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ $pct >= 0 ? '+' : '' }}{{ $pct }}%
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @php($pct = $change($current['net_profit'], $previous['net_profit']))
                        <tr class="bg-zinc-100 text-base font-bold dark:bg-zinc-800">
                            <td class="px-4 py-4">
                                <div class="flex items-start gap-2">
                                    <span class="w-4 shrink-0 text-center font-mono text-zinc-400">=</span>
                                    <div>
                                        <div class="text-zinc-900 dark:text-white">Net profit</div>
                                        <div class="text-xs font-normal text-zinc-500 dark:text-zinc-400">What the shop kept after everything.</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right tabular-nums {{ $current['net_profit'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-300' }}">{{ money($current['net_profit']) }}</td>
                            <td class="px-4 py-4 text-right tabular-nums text-zinc-500">{{ money($previous['net_profit']) }}</td>
                            <td class="px-4 py-4 text-right text-xs tabular-nums">
                                @if($pct === null)
                                    <span class="text-zinc-400">—</span>
                                @else
                                    <span class="{{ $pct >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ $pct >= 0 ? '+' : '' }}{{ $pct }}%</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-1 border-t border-zinc-200 p-4 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                <div>Total billed incl. VAT (gross sales): <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200">{{ money($current['gross_sales']) }}</span></div>
                <div>VAT collected: <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200">{{ money($current['tax']) }}</span> — not your income; it is owed to the government, so it is left out of profit.</div>
                <div>Courier: charged customers <span class="tabular-nums">{{ money($current['courier_charge']) }}</span>, paid couriers <span class="tabular-nums">{{ money($current['courier_cost']) }}</span>.</div>
                <div>Draft, cancelled and parked (held) sales are not counted. Till cash in / out moves are not expenses.</div>
            </div>
        </flux:card>

        <div class="space-y-6">

            {{-- Expenses by category --}}
            <flux:card>
                <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading>Expenses by category</flux:heading>
                    <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('expenses.index')" wire:navigate class="print:hidden">View</flux:button>
                </div>
                @if(empty($current['expenses']))
                    <div class="flex flex-col items-center gap-2 p-8 text-center">
                        <flux:icon name="receipt-percent" class="size-8 text-zinc-300" />
                        <flux:text class="text-zinc-400">No expenses recorded in this period</flux:text>
                        <flux:button size="sm" :href="route('expenses.create')" wire:navigate class="print:hidden">Record an expense</flux:button>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($current['expenses'] as $row)
                            @php($share = $current['expense_total'] > 0 ? $row['total'] / $current['expense_total'] * 100 : 0)
                            <div class="px-4 py-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-800 dark:text-zinc-200">{{ $row['category'] }}</span>
                                    <span class="font-medium tabular-nums">{{ money($row['total']) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-rose-400" style="width: {{ round($share, 1) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>

        </div>
    </div>

    {{-- Gross profit by product category --}}
    <flux:card class="mt-6">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>Gross profit by product category</flux:heading>
            <flux:text class="text-xs text-zinc-500">Item sales after item discounts (before bill discounts and VAT), less what those items cost, net of returns.</flux:text>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column class="text-right">Qty sold</flux:table.column>
                <flux:table.column class="text-right">Sales</flux:table.column>
                <flux:table.column class="text-right">Cost</flux:table.column>
                <flux:table.column class="text-right">Gross profit</flux:table.column>
                <flux:table.column class="text-right">Margin</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($categories as $row)
                    <flux:table.row wire:key="cat-{{ $row['category'] }}">
                        <flux:table.cell class="font-medium">{{ $row['category'] }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ rtrim(rtrim(number_format($row['qty'], 3), '0'), '.') }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ money($row['sales']) }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums text-zinc-500">{{ money($row['cost']) }}</flux:table.cell>
                        <flux:table.cell class="text-right font-semibold tabular-nums {{ $tone($row['profit']) }}">{{ money($row['profit']) }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ $row['margin'] !== null ? $row['margin'].'%' : '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">
                            <flux:text class="text-zinc-400">No sales in this period</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

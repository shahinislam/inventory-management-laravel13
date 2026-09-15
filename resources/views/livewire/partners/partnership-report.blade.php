<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Partnership Report</flux:heading>
            <flux:text class="mt-1">Profit and partner capital accounts</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button icon="users" variant="ghost" :href="route('partners.index')" wire:navigate>Partners</flux:button>
            <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
            @if (! $closed)
                <flux:button icon="lock-closed" variant="primary" wire:click="$set('showCloseModal', true)">
                    Close Period
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Period heading for the printed copy, which hides the filter bar. --}}
    <div class="mb-6 hidden print:block">
        <flux:heading size="lg">Partnership Report</flux:heading>
        <flux:text>
            {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
            to {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </flux:text>
    </div>

    {{-- Filter --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="period" class="w-40">
                <flux:select.option value="today">Today</flux:select.option>
                <flux:select.option value="this_week">This Week</flux:select.option>
                <flux:select.option value="this_month">This Month</flux:select.option>
                <flux:select.option value="last_month">Last Month</flux:select.option>
                <flux:select.option value="this_year">This Year</flux:select.option>
                <flux:select.option value="custom">Custom Range</flux:select.option>
            </flux:select>

            @if ($period === 'custom')
                <flux:input wire:model.live="dateFrom" type="date" />
                <flux:text>to</flux:text>
                <flux:input wire:model.live="dateTo" type="date" />
            @endif

            @if ($closed)
                <flux:badge color="zinc" icon="lock-closed">
                    Closed {{ $closed->closed_at?->format('d M Y') }}
                    @if ($closed->closedBy)
                        by {{ $closed->closedBy->name }}
                    @endif
                </flux:badge>
            @endif
        </div>
    </flux:card>

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-tile label="Sales" :value="money($summary['total_sales'])" tone="green" icon="banknotes"
            :hint="$summary['invoice_count'] . ' invoices'" />
        <x-stat-tile label="Purchases" :value="money($summary['total_purchases'])" tone="red" icon="shopping-cart"
            :hint="$summary['purchase_count'] . ' orders'" />
        <x-stat-tile label="Courier Margin" :value="money($summary['courier_margin'])"
            :tone="$summary['courier_margin'] < 0 ? 'yellow' : 'blue'" icon="truck"
            :hint="$summary['courier_margin'] < 0 ? 'Shop absorbed delivery' : 'Charged above cost'" />
        <x-stat-tile label="Profit" :value="money($summary['profit'])"
            :tone="$summary['profit'] < 0 ? 'red' : 'green'" icon="chart-bar" />
    </div>

    {{-- Cash basis is a real limitation, not a footnote: stock bought this
         period is expensed immediately even though it is still on the shelf. --}}
    <flux:card class="mb-6 p-4">
        <div class="flex items-start gap-3">
            <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
            <flux:text size="sm">
                Cash basis: profit is sales received less purchases made in this period, plus the margin on
                delivery. Stock bought but not yet sold counts as a cost now, so a heavy purchasing month can
                show a loss while the stock is still in hand.
            </flux:text>
        </div>
    </flux:card>

    @if (round($totalShare, 2) !== 100.0)
        <flux:card class="mb-6 border-amber-300 p-4 dark:border-amber-800 print:hidden">
            <div class="flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <flux:text size="sm" class="text-amber-700 dark:text-amber-400">
                    Active shares total {{ rtrim(rtrim(number_format($totalShare, 2), '0'), '.') }}%, not 100% —
                    the profit split below is incomplete until they add up.
                </flux:text>
            </div>
        </flux:card>
    @endif

    {{-- Per-partner breakdown --}}
    <flux:card class="mb-6">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>Partner Accounts</flux:heading>
            <flux:text size="sm" class="mt-1">
                Investment and withdrawal totals are lifetime figures; the profit share is for the selected period.
            </flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Partner</flux:table.column>
                <flux:table.column>Share</flux:table.column>
                <flux:table.column>Investment</flux:table.column>
                <flux:table.column>Profit Share</flux:table.column>
                <flux:table.column>Withdrawn</flux:table.column>
                <flux:table.column>Balance</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row wire:key="row-{{ $row['partner']->id }}">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $row['partner']->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums">
                                {{ rtrim(rtrim(number_format($row['share'], 2), '0'), '.') }}%
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums">{{ money($row['invested']) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span @class([
                                'tabular-nums font-medium',
                                'text-green-600 dark:text-green-400' => $row['profit_share'] >= 0,
                                'text-red-600 dark:text-red-400' => $row['profit_share'] < 0,
                            ])>{{ money($row['profit_share']) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums text-amber-600 dark:text-amber-400">
                                {{ $row['withdrawn'] > 0 ? '−' : '' }}{{ money($row['withdrawn']) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-semibold tabular-nums text-zinc-900 dark:text-white">
                                {{ money($row['balance']) }}
                            </span>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center">
                            <flux:text>No active partners. Add one to see the profit split.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Settled periods --}}
    @if ($recentPeriods->isNotEmpty())
        <flux:card class="print:hidden">
            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>Closed Periods</flux:heading>
                <flux:text size="sm" class="mt-1">
                    Locked figures. Later edits to the underlying invoices do not change these.
                </flux:text>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Period</flux:table.column>
                    <flux:table.column>Sales</flux:table.column>
                    <flux:table.column>Purchases</flux:table.column>
                    <flux:table.column>Courier</flux:table.column>
                    <flux:table.column>Profit</flux:table.column>
                    <flux:table.column>Closed</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($recentPeriods as $p)
                        <flux:table.row wire:key="period-{{ $p->id }}">
                            <flux:table.cell>
                                {{ $p->period_start->format('d M Y') }} – {{ $p->period_end->format('d M Y') }}
                            </flux:table.cell>
                            <flux:table.cell><span class="tabular-nums">{{ money($p->total_sales) }}</span></flux:table.cell>
                            <flux:table.cell><span class="tabular-nums">{{ money($p->total_purchases) }}</span></flux:table.cell>
                            <flux:table.cell><span class="tabular-nums">{{ money($p->courier_margin) }}</span></flux:table.cell>
                            <flux:table.cell>
                                <span @class([
                                    'font-medium tabular-nums',
                                    'text-green-600 dark:text-green-400' => $p->profit >= 0,
                                    'text-red-600 dark:text-red-400' => $p->profit < 0,
                                ])>{{ money($p->profit) }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">
                                    {{ $p->closed_at?->format('d M Y') ?? '—' }}
                                </span>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    {{-- Close period confirmation --}}
    <flux:modal wire:model="showCloseModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg">Close this period?</flux:heading>
            <flux:text class="mt-2">
                This locks the profit of {{ money($summary['profit']) }} for
                {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} –
                {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
                and each partner's share at their current percentage. Later edits to these invoices will not
                change the locked figures.
            </flux:text>

            <flux:field class="mt-4">
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="closeNotes" rows="2" placeholder="optional" />
            </flux:field>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showCloseModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="closePeriod">Close Period</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

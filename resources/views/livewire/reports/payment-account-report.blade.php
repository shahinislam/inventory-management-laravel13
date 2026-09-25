<div class="p-4" data-print-report="Account Report">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Account Report</flux:heading>
            <flux:text class="mt-1">Money in and out by bank account, card and wallet</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Filters --}}
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

            <flux:select wire:model.live="accountId" class="w-56">
                <flux:select.option value="">All Accounts</flux:select.option>
                @foreach ($accountOptions as $option)
                    <flux:select.option value="{{ $option->id }}">{{ $option->display_name }}{{ $option->trashed() ? ' (deleted)' : '' }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Money In (Sales)" :value="money($summary['in'])" tone="green" icon="arrow-down-left" />
        <x-stat-tile label="Money Out (Purchases)" :value="money($summary['out'])" tone="red" icon="arrow-up-right" />
        <x-stat-tile label="Net" :value="money($summary['net'])" :tone="$summary['net'] >= 0 ? 'blue' : 'red'" icon="scale" />
    </div>

    {{-- Per-account summary --}}
    <flux:card class="mb-6">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>By Account</flux:heading>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Payments</flux:table.column>
                <flux:table.column align="end">In</flux:table.column>
                <flux:table.column align="end">Out</flux:table.column>
                <flux:table.column align="end">Net</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($accounts as $row)
                    <flux:table.row wire:key="acc-{{ $row->account->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $row->account->display_name }}</flux:text>
                            @if ($row->account->trashed())
                                <flux:text class="text-xs text-zinc-400">Deleted</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:text class="text-sm">{{ $row->account->type_label }}</flux:text></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="zinc">{{ $row->count }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums text-green-600 dark:text-green-400">{{ money($row->in) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums text-red-600 dark:text-red-400">{{ money($row->out) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ money($row->in - $row->out) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">
                            <flux:text class="text-zinc-400">No payment accounts yet</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Transactions --}}
    <flux:card>
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>Transactions</flux:heading>
        </div>
        <flux:table :paginate="$transactions">
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Payment</flux:table.column>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column>For</flux:table.column>
                <flux:table.column align="end">In</flux:table.column>
                <flux:table.column align="end">Out</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transactions as $tx)
                    <flux:table.row wire:key="tx-{{ $tx->direction }}-{{ $tx->id }}">
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ \Carbon\Carbon::parse($tx->payment_date)->format('d M Y') }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="font-mono text-xs">{{ $tx->payment_number }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">
                                {{ ucfirst(str_replace('_', ' ', $tx->method)) }}@if ($tx->reference) · {{ $tx->reference }}@endif
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $accountNames[$tx->payment_account_id] ?? '—' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($tx->direction === 'in')
                                <a href="{{ route('invoices.show', $tx->doc_id) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $tx->doc_number }}</a>
                            @else
                                <a href="{{ route('purchases.edit', $tx->doc_id) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $tx->doc_number }}</a>
                            @endif
                            <flux:text class="text-xs text-zinc-400">{{ $tx->party ?? '—' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums text-green-600 dark:text-green-400">
                            {{ $tx->direction === 'in' ? money($tx->amount) : '' }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums text-red-600 dark:text-red-400">
                            {{ $tx->direction === 'out' ? money($tx->amount) : '' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">
                            <flux:text class="text-zinc-400">No account payments in this period</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

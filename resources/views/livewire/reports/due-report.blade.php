<div class="p-4" data-print-report="Due Report">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Due Report</flux:heading>
            <flux:text class="mt-1">What customers owe you and what you owe suppliers</flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-tile label="Customer Dues" :value="money($summary['receivable'])" tone="blue" icon="arrow-down-left" />
        <x-stat-tile label="Customer Overdue" :value="money($summary['receivable_overdue'])" tone="red" icon="exclamation-triangle" />
        <x-stat-tile label="Supplier Dues" :value="money($summary['payable'])" tone="yellow" icon="arrow-up-right" />
        <x-stat-tile label="Supplier Overdue" :value="money($summary['payable_overdue'])" tone="red" icon="exclamation-triangle" />
    </div>

    {{-- Tabs & Filters --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="flex flex-wrap items-center gap-3">
            <flux:radio.group wire:model.live="tab" variant="segmented">
                <flux:radio value="customers" label="Customer Dues" />
                <flux:radio value="suppliers" label="Supplier Dues" />
            </flux:radio.group>

            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="$tab === 'suppliers' ? 'Search supplier or PO number...' : 'Search customer, phone or invoice...'"
                icon="magnifying-glass"
                class="min-w-48 flex-1" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />

            <flux:checkbox wire:model.live="overdueOnly" label="Overdue only" />
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>{{ $tab === 'suppliers' ? 'Supplier Dues' : 'Customer Dues' }}</flux:heading>
            <flux:text class="text-sm">
                Total due: <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">{{ money($filteredTotal) }}</span>
            </flux:text>
        </div>

        <flux:table :paginate="$rows">
            <flux:table.columns>
                <flux:table.column>{{ $tab === 'suppliers' ? 'Supplier' : 'Customer' }}</flux:table.column>
                <flux:table.column>{{ $tab === 'suppliers' ? 'Purchase Order' : 'Invoice' }}</flux:table.column>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Due Date</flux:table.column>
                <flux:table.column align="end">Total</flux:table.column>
                <flux:table.column align="end">Paid</flux:table.column>
                <flux:table.column align="end">Due</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $row)
                    @php
                        $isSupplier = $tab === 'suppliers';
                        $dueDate = $isSupplier ? $row->payment_due_date : $row->due_date;
                        $overdueDays = $dueDate && $dueDate->lt(today()) ? (int) $dueDate->diffInDays(today()) : 0;
                    @endphp
                    <flux:table.row wire:key="{{ $tab }}-{{ $row->id }}">
                        <flux:table.cell>
                            @if ($isSupplier)
                                <flux:text class="font-medium">{{ $row->supplier?->name ?? '—' }}</flux:text>
                                @if ($row->supplier?->phone)
                                    <flux:text class="text-xs text-zinc-400">{{ $row->supplier->phone }}</flux:text>
                                @endif
                            @else
                                <flux:text class="font-medium">{{ $row->customer_name }}</flux:text>
                                @if ($row->customer_phone)
                                    <flux:text class="text-xs text-zinc-400">{{ $row->customer_phone }}</flux:text>
                                @endif
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($isSupplier)
                                <a href="{{ route('purchases.edit', $row) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $row->order_number }}</a>
                            @else
                                <a href="{{ route('invoices.show', $row) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $row->invoice_number }}</a>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ ($isSupplier ? $row->order_date : $row->invoice_date)->format('d M Y') }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($dueDate)
                                <flux:text class="text-sm">{{ $dueDate->format('d M Y') }}</flux:text>
                                @if ($overdueDays > 0)
                                    <flux:badge size="sm" color="red">{{ $overdueDays }} {{ Str::plural('day', $overdueDays) }} overdue</flux:badge>
                                @endif
                            @else
                                <flux:text class="text-sm text-zinc-400">—</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end" class="tabular-nums">{{ money($row->total) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums text-green-600 dark:text-green-400">{{ money($row->paid_amount) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums text-red-600 dark:text-red-400">
                            {{ money($row->due_amount) }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="check-circle" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No dues found</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

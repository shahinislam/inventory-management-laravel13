<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Invoices</flux:heading>
            <flux:text class="mt-1">Manage sales invoices & payments</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="shopping-cart" variant="outline" href="{{ route('pos') }}" wire:navigate>POS Terminal</flux:button>
            <flux:button icon="plus" href="{{ route('invoices.create') }}" wire:navigate>New Invoice</flux:button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Today's Sales" :value="money($summary['total_today'])" tone="green" icon="banknotes" />
        <x-stat-tile label="Pending Amount" :value="money($summary['total_pending'])" tone="yellow" icon="clock" />
        <x-stat-tile label="Overdue Invoices" :value="$summary['total_overdue']" tone="red"
            icon="exclamation-triangle" />
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_auto]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search invoice #, customer name or phone..."
                icon="magnifying-glass" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />
            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="sent">Sent</flux:select.option>
                <flux:select.option value="paid">Paid</flux:select.option>
                <flux:select.option value="partial">Partial</flux:select.option>
                <flux:select.option value="overdue">Overdue</flux:select.option>
                <flux:select.option value="cancelled">Cancelled</flux:select.option>
            </flux:select>
            <flux:input wire:model.live="dateFrom" type="date" />
            <flux:input wire:model.live="dateTo" type="date" />
            <flux:button icon="x-mark" variant="ghost" wire:click="resetFilters" square />
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$invoices">
            <flux:table.columns>
                <flux:table.column>Invoice #</flux:table.column>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column>Items</flux:table.column>
                <flux:table.column>Total</flux:table.column>
                <flux:table.column>Due</flux:table.column>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($invoices as $invoice)
                    <flux:table.row wire:key="{{ $invoice->id }}">

                        <flux:table.cell>
                            <flux:text class="font-mono font-medium">{{ $invoice->invoice_number }}</flux:text>
                            <flux:text class="text-xs text-zinc-400">{{ $invoice->createdBy->name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $invoice->customer_name }}</flux:text>
                            @if($invoice->customer_phone)
                                <flux:text class="text-xs text-zinc-400">{{ $invoice->customer_phone }}</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $invoice->items_count }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="font-medium tabular-nums">{{ money($invoice->total) }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($invoice->due_amount > 0)
                                <flux:text class="text-sm tabular-nums text-red-500">{{ money($invoice->due_amount) }}</flux:text>
                            @else
                                <flux:text class="text-sm text-green-500">Paid</flux:text>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $invoice->invoice_date->format('d M Y') }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="match($invoice->status) {
                                    'paid'      => 'green',
                                    'partial'   => 'yellow',
                                    'overdue'   => 'red',
                                    'cancelled' => 'zinc',
                                    'sent'      => 'blue',
                                    default     => 'zinc'
                                }"
                            >{{ ucfirst($invoice->status) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('invoices.show', $invoice)" wire:navigate>View / Print</flux:menu.item>
                                    @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                                        <flux:menu.item icon="pencil" :href="route('invoices.edit', $invoice)" wire:navigate>Edit</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="document-text" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No invoices found</flux:text>
                                <flux:button size="sm" href="{{ route('invoices.create') }}" wire:navigate>Create your first invoice</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

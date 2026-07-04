<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Invoices</flux:heading>
            <flux:text class="mt-1">Manage sales invoices & payments</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="shopping-cart" variant="outline" href="{{ route('pos') }}" wire:navigate>POS Terminal</flux:button>
            <flux:button icon="plus" href="{{ route('invoices.create') }}" wire:navigate>New Invoice</flux:button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem" class="mb-6">
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">Today's Sales</flux:text>
            <flux:heading size="lg" class="mt-1 text-green-600 dark:text-green-400">${{ number_format($summary['total_today'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">Pending Amount</flux:text>
            <flux:heading size="lg" class="mt-1 text-yellow-600 dark:text-yellow-400">${{ number_format($summary['total_pending'], 2) }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text class="text-sm text-zinc-500">Overdue Invoices</flux:text>
            <flux:heading size="lg" class="mt-1 text-red-600 dark:text-red-400">{{ $summary['total_overdue'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:0.75rem">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search invoice #, customer name or phone..."
                icon="magnifying-glass"
            />
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
                            <flux:text class="font-medium">{{ $invoice->invoice_number }}</flux:text>
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
                            <flux:text class="font-medium">${{ number_format($invoice->total, 2) }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($invoice->due_amount > 0)
                                <flux:text class="text-sm text-red-500">${{ number_format($invoice->due_amount, 2) }}</flux:text>
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

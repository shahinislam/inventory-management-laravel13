<div class="p-4">

    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('pos')" wire:navigate />
        <div>
            <flux:heading size="xl">Return items</flux:heading>
            <flux:text class="mt-1">Find the sale, choose what came back, and how the money is returned.</flux:text>
        </div>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif

    @php $sale = $this->sale; @endphp

    @if (! $sale)
        {{-- Step 1: find the sale --}}
        <flux:card class="mx-auto max-w-2xl p-6">
            <flux:heading class="mb-1">1. Find the sale</flux:heading>
            <flux:text class="mb-4 text-sm">Scan the receipt barcode, or type the receipt number, phone or name.</flux:text>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" autofocus
                placeholder="e.g. INV-2026-0042 or 01711000001" />

            <div class="mt-3 space-y-2">
                @foreach ($this->matches as $m)
                    <button type="button" wire:key="m-{{ $m->id }}" wire:click="selectInvoice({{ $m->id }})"
                        class="flex w-full items-center justify-between rounded-lg border border-zinc-200 px-3 py-2 text-left hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-mono text-sm font-medium">{{ $m->invoice_number }}</div>
                            <div class="text-xs text-zinc-500">{{ $m->invoice_date->format('d M Y') }} · {{ $m->customer_name }} {{ $m->customer_phone ? '· '.$m->customer_phone : '' }}</div>
                        </div>
                        <span class="font-semibold tabular-nums">{{ money($m->total) }}</span>
                    </button>
                @endforeach
                @if (mb_strlen(trim($search)) >= 2 && $this->matches->isEmpty())
                    <flux:text class="py-4 text-center text-sm text-zinc-400">No sale matches “{{ $search }}”.</flux:text>
                @endif
            </div>
        </flux:card>
    @else
        <form wire:submit="submit">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">
                <flux:card class="p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <flux:heading>2. What came back?</flux:heading>
                            <flux:text class="text-sm">
                                <span class="font-mono">{{ $sale->invoice_number }}</span> · {{ $sale->invoice_date->format('d M Y') }}
                                · {{ $sale->customer_name }} {{ $sale->customer_phone ? '· '.$sale->customer_phone : '' }}
                            </flux:text>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="subtle" wire:click="returnAll" type="button">Return everything</flux:button>
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearInvoice" type="button">Other sale</flux:button>
                        </div>
                    </div>

                    <flux:error name="lines" />

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Item</flux:table.column>
                            <flux:table.column class="text-right">Sold</flux:table.column>
                            <flux:table.column class="text-right">Returned</flux:table.column>
                            <flux:table.column class="w-32">Return now</flux:table.column>
                            <flux:table.column>Back to shelf?</flux:table.column>
                            <flux:table.column class="text-right">Refund</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($sale->items as $item)
                                @php
                                    $done = (float) $item->returnItems->sum('quantity');
                                    $left = max(0, $item->quantity - $done);
                                    $unit = $item->product?->unit;
                                    $loose = $item->product?->isLoose();
                                @endphp
                                <flux:table.row wire:key="ln-{{ $item->id }}">
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $item->product_name }}</div>
                                        <div class="text-xs text-zinc-500">{{ money($this->unitRefunds[$item->id] ?? 0) }} each{{ $item->is_gift ? ' · gift' : '' }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right tabular-nums">{{ format_qty($item->quantity, $unit) }}</flux:table.cell>
                                    <flux:table.cell class="text-right tabular-nums">{{ $done > 0 ? format_qty($done) : '—' }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($left > 0)
                                            <flux:input wire:model.live.debounce.400ms="lines.{{ $item->id }}.quantity" type="number"
                                                :step="$loose ? '0.001' : '1'" min="0" :max="$left" size="sm" placeholder="0" />
                                            <flux:error name="lines.{{ $item->id }}.quantity" />
                                        @else
                                            <flux:text class="text-xs text-zinc-400">All returned</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($left > 0)
                                            <flux:switch wire:model="lines.{{ $item->id }}.restock" />
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right tabular-nums">
                                        {{ money(((float) ($lines[$item->id]['quantity'] ?? 0) ?: 0) * ($this->unitRefunds[$item->id] ?? 0)) }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    <flux:text class="mt-3 text-xs text-zinc-500">Turn “Back to shelf” off for damaged goods: they're refunded but not added back to stock.</flux:text>
                </flux:card>

                <div class="space-y-6">
                    <flux:card class="p-6">
                        <flux:heading class="mb-4">3. Money back</flux:heading>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-zinc-500">Value returned</span><span class="font-semibold tabular-nums">{{ money($this->returnValue) }}</span></div>
                            @if ($this->dueReduced > 0)
                                <div class="flex justify-between"><span class="text-zinc-500">Taken off their due</span><span class="tabular-nums">−{{ money($this->dueReduced) }}</span></div>
                            @endif
                            <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700">
                                <span>Give back</span><span class="tabular-nums">{{ money($this->refundAmount) }}</span>
                            </div>
                        </div>

                        @if ($this->refundAmount > 0)
                            <div class="mt-4 space-y-3">
                                <flux:radio.group wire:model.live="refundMethod" label="Refund by">
                                    @foreach (App\Services\SalesReturnService::REFUND_METHODS as $m)
                                        <flux:radio value="{{ $m }}" label="{{ App\Models\Payment::methodLabel($m) }}" />
                                    @endforeach
                                </flux:radio.group>
                                <flux:error name="refundMethod" />

                                @if ($refundMethod === 'cash' && ! $hasShift)
                                    <flux:text class="text-xs text-amber-600">Cash refunds come out of the drawer — open a shift in the POS first.</flux:text>
                                @endif

                                @if (App\Models\PaymentAccount::requiredFor($refundMethod))
                                    <flux:select wire:model="paymentAccountId" placeholder="Paid from account...">
                                        @foreach ($accounts as $a)
                                            <flux:select.option value="{{ $a->id }}">{{ $a->display_name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="paymentAccountId" />
                                @endif
                                @if (App\Models\Payment::needsReference($refundMethod))
                                    <flux:input wire:model="reference" placeholder="bKash / Nagad transaction ID" />
                                    <flux:error name="reference" />
                                @endif
                            </div>
                        @elseif ($this->returnValue > 0)
                            <flux:text class="mt-3 text-sm text-green-700 dark:text-green-400">Nothing to pay out — the amount comes off what the customer still owes.</flux:text>
                        @endif

                        <flux:input wire:model="reason" class="mt-4" placeholder="Reason (optional), e.g. damaged pack" />

                        <flux:button type="submit" variant="primary" icon="arrow-uturn-left" class="mt-4 w-full"
                            :disabled="$this->returnValue <= 0">Save return</flux:button>
                    </flux:card>
                </div>
            </div>
        </form>
    @endif
</div>

<div class="p-4 print:p-0">

    @if ($return)
        {{-- ============ SHOW: a recorded return, printable ============ --}}
        @php
            $refunded = (float) $return->paid_amount;
        @endphp

        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
            <div class="flex items-center gap-4">
                <flux:button icon="arrow-left" variant="ghost"
                    href="{{ $return->parent ? route('purchases.edit', $return->parent) : route('purchases.index', ['kind' => 'returns']) }}" wire:navigate />
                <div>
                    <flux:heading size="xl">{{ $return->order_number }}</flux:heading>
                    <flux:text class="mt-1">Return to supplier</flux:text>
                </div>
            </div>
            <flux:button icon="printer" x-on:click="window.print()">Print</flux:button>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400 print:hidden">{{ session('success') }}</div>
        @endif

        <div class="mx-auto max-w-4xl rounded-xl border border-zinc-200 bg-white p-8 dark:border-zinc-800 dark:bg-zinc-900 print:max-w-none print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Purchase return</div>
                    <div class="mt-1 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white print:text-black">{{ $return->order_number }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ $return->order_date->format('d M Y') }}</div>
                </div>
                <div class="text-sm sm:text-right">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Supplier</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white print:text-black">{{ $return->supplier?->name ?? '—' }}</div>
                    @if ($return->supplier?->phone)
                        <div class="text-zinc-500">{{ $return->supplier->phone }}</div>
                    @endif
                    @if ($return->parent)
                        <div class="mt-2 text-zinc-500">
                            Against
                            <a href="{{ route('purchases.edit', $return->parent) }}" wire:navigate class="font-mono hover:underline">{{ $return->parent->order_number }}</a>
                        </div>
                    @endif
                    @if ($return->warehouse)
                        <div class="text-zinc-500">From {{ $return->warehouse->name }}</div>
                    @endif
                </div>
            </div>

            <div class="mt-8 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-700">
                            <th class="py-2 text-left font-semibold">Product</th>
                            <th class="py-2 text-right font-semibold">Qty</th>
                            <th class="py-2 text-right font-semibold">Unit cost</th>
                            <th class="py-2 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($return->items as $item)
                            @php $label = $item->unit_label ?: $item->product->unit; @endphp
                            <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800" wire:key="ri-{{ $item->id }}">
                                <td class="py-2.5">
                                    <div class="font-medium text-zinc-900 dark:text-white print:text-black">{{ $item->product->name }}</div>
                                    <div class="font-mono text-xs text-zinc-500">{{ $item->product->sku }}</div>
                                </td>
                                <td class="py-2.5 text-right tabular-nums">
                                    {{ format_qty($item->quantity, $label) }}
                                    @if ($item->factor > 1)
                                        <div class="text-xs text-zinc-500">= {{ format_qty($item->quantity * $item->factor, $item->product->unit) }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 text-right tabular-nums">{{ money($item->unit_cost) }} <span class="text-xs text-zinc-500">/ {{ $label }}</span></td>
                                <td class="py-2.5 text-right font-medium tabular-nums">{{ money($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <div class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-800">
                        <span class="font-medium uppercase tracking-wide text-zinc-500">Return total</span>
                        <span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white print:text-black">{{ money($return->total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Refunded by supplier</span>
                        <span class="tabular-nums text-green-600">{{ money($refunded) }}</span>
                    </div>
                </div>
            </div>

            @if ($return->notes)
                <div class="mt-6 text-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Reason</div>
                    <div class="mt-1 text-zinc-700 dark:text-zinc-300 print:text-black">{{ $return->notes }}</div>
                </div>
            @endif

            @if ($return->payments->isNotEmpty())
                <div class="mt-6 text-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Refunds received</div>
                    <div class="mt-2 space-y-1">
                        @foreach ($return->payments as $payment)
                            <div class="flex justify-between gap-2" wire:key="rp-{{ $payment->id }}">
                                <span>
                                    {{ $payment->payment_date->format('d M Y') }} · {{ App\Models\Payment::methodLabel($payment->method) }}
                                    @if ($payment->paymentAccount) · {{ $payment->paymentAccount->display_name }} @endif
                                    @if ($payment->reference) · {{ $payment->reference }} @endif
                                </span>
                                <span class="tabular-nums">{{ money($payment->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-8 text-xs text-zinc-400">Recorded by {{ $return->createdBy?->name ?? '—' }} on {{ $return->created_at->format('d M Y, h:i A') }}</div>
        </div>
    @elseif ($order)
        {{-- ============ CREATE: return goods from a purchase order ============ --}}
        <div class="mb-6 flex items-center gap-4">
            <flux:button icon="arrow-left" variant="ghost" href="{{ route('purchases.edit', $order) }}" wire:navigate />
            <div>
                <flux:heading size="xl">Return to supplier</flux:heading>
                <flux:text class="mt-1">{{ $order->order_number }} · {{ $order->supplier?->name }} · {{ $order->warehouse?->name ?? 'No warehouse' }}</flux:text>
            </div>
        </div>

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <flux:card class="min-w-0 p-6">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <flux:heading>Items</flux:heading>
                    @if (count($lines))
                        <flux:button size="sm" variant="ghost" wire:click="returnAll">Return all</flux:button>
                    @endif
                </div>

                @if (empty($lines))
                    <flux:text class="py-8 text-center text-zinc-500">Nothing left to return on this order: no received stock, or it has all been returned already.</flux:text>
                @else
                    <div class="-mx-2 overflow-x-auto">
                        <table class="w-full min-w-lg text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-700">
                                    <th class="px-2 py-2.5 text-left font-semibold">Product</th>
                                    <th class="px-2 py-2.5 text-right font-semibold">Returnable</th>
                                    <th class="px-2 py-2.5 text-right font-semibold">In stock</th>
                                    <th class="px-2 py-2.5 text-right font-semibold">Unit cost</th>
                                    <th class="w-28 px-2 py-2.5 text-right font-semibold">Return qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    @php $fraction = $line['loose'] && $line['factor'] <= 1; @endphp
                                    <tr wire:key="line-{{ $line['item_id'] }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-800/70">
                                        <td class="px-2 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $line['name'] }}</div>
                                            <div class="font-mono text-xs text-zinc-500">{{ $line['sku'] }}</div>
                                        </td>
                                        <td class="px-2 py-3 text-right tabular-nums">
                                            {{ format_qty($line['returnable'], $line['unit_label']) }}
                                            @if ($line['factor'] > 1)
                                                <div class="text-xs text-zinc-500">1 {{ $line['unit_label'] }} = {{ format_qty($line['factor'], $line['unit']) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-3 text-right tabular-nums">{{ format_qty($line['available'], $line['unit']) }}</td>
                                        <td class="px-2 py-3 text-right tabular-nums">{{ money($line['unit_cost']) }}</td>
                                        <td class="px-2 py-3 text-right">
                                            <flux:input wire:model.live.debounce.400ms="lines.{{ $i }}.qty" type="number" min="0"
                                                step="{{ $fraction ? '0.001' : '1' }}" max="{{ $line['returnable'] }}"
                                                size="sm" class="text-right tabular-nums" placeholder="0" />
                                            @error("lines.$i.qty") <div class="mt-0.5 text-xs text-red-500">{{ $message }}</div> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @error('lines') <flux:text class="mt-2 text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </flux:card>

            <div class="space-y-4 lg:self-start">
                <flux:card class="space-y-4 p-6">
                    <flux:field>
                        <flux:label badge="Required">Return date</flux:label>
                        <flux:input wire:model="return_date" type="date" />
                        <flux:error name="return_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Reason</flux:label>
                        <flux:textarea wire:model="reason" rows="2" placeholder="Damaged, expired, wrong item..." />
                        <flux:error name="reason" />
                    </flux:field>

                    <div class="flex items-baseline justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                        <span class="text-sm font-medium uppercase tracking-wide text-zinc-500">Return total</span>
                        <span class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ money($this->returnTotal) }}</span>
                    </div>
                    <flux:text class="text-xs text-zinc-500">Taken off what you owe on {{ $order->order_number }}.</flux:text>
                </flux:card>

                <flux:card class="space-y-4 p-6">
                    <flux:heading>Supplier refunded now</flux:heading>
                    <flux:field>
                        <flux:label>Amount <flux:text class="text-xs text-zinc-400">(optional)</flux:text></flux:label>
                        <flux:input wire:model="refund_amount" type="number" step="0.01" min="0" prefix="৳" class="tabular-nums" />
                        <flux:error name="refund_amount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Method</flux:label>
                        <flux:select wire:model.live="refund_method">
                            @foreach (App\Models\Payment::METHODS as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="refund_method" />
                    </flux:field>

                    @if (App\Models\PaymentAccount::requiredFor($refund_method))
                        <flux:field>
                            <flux:label>Received into</flux:label>
                            <flux:select wire:model="refund_account_id" placeholder="Select account...">
                                @foreach ($refundAccounts as $account)
                                    <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="refund_account_id" />
                        </flux:field>
                    @endif

                    <flux:field>
                        @if (App\Models\Payment::needsReference($refund_method))
                            <flux:label>Transaction ID</flux:label>
                        @else
                            <flux:label>Reference <flux:text class="text-xs text-zinc-400">(optional)</flux:text></flux:label>
                        @endif
                        <flux:input wire:model="refund_reference" />
                        <flux:error name="refund_reference" />
                    </flux:field>
                </flux:card>

                <flux:button variant="primary" class="w-full" icon="arrow-uturn-left" wire:click="save"
                    :disabled="empty($lines)" wire:confirm="Record this return? Stock will be removed from the warehouse.">
                    Record return
                </flux:button>
            </div>
        </div>
    @endif

</div>

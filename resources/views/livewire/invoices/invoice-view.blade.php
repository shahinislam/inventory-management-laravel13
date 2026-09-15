{{--
    Flux keeps a `dark` class on <html>, which would print white-on-black and
    drain toner. Drop it for the duration of the print job and restore whatever
    it was afterwards.
--}}
<div class="p-6 print:p-0" x-data="{
    wasDark: false,
    init() {
        this.$el._before = () => {
            this.wasDark = document.documentElement.classList.contains('dark');
            document.documentElement.classList.remove('dark');
        };
        this.$el._after = () => {
            if (this.wasDark) document.documentElement.classList.add('dark');
        };
        window.addEventListener('beforeprint', this.$el._before);
        window.addEventListener('afterprint', this.$el._after);
    },
    destroy() {
        window.removeEventListener('beforeprint', this.$el._before);
        window.removeEventListener('afterprint', this.$el._after);
    }
}">

    {{-- ============ TOOLBAR (screen only) ============ --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" variant="subtle" square href="{{ route('invoices.index') }}"
                wire:navigate />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" class="font-mono tracking-tight">{{ $invoice->invoice_number }}
                    </flux:heading>
                    <flux:badge size="sm" :color="match ($invoice->status) {
                        'paid' => 'green',
                        'partial' => 'yellow',
                        'overdue' => 'red',
                        'cancelled' => 'zinc',
                        'sent' => 'blue',
                        default => 'zinc',
                    }" :icon="match ($invoice->status) {
                        'paid' => 'check-circle',
                        'overdue' => 'exclamation-triangle',
                        'cancelled' => 'x-circle',
                        default => null,
                    }">
                        {{ ucfirst($invoice->status) }}</flux:badge>
                </div>
                <flux:text class="mt-0.5 text-sm text-zinc-500">
                    {{ $invoice->invoice_date->format('d M Y') }} · {{ $invoice->customer_name }}
                </flux:text>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($invoice->due_amount > 0 && $invoice->status !== 'cancelled')
                <flux:button icon="banknotes" variant="primary" wire:click="openPaymentModal">Record Payment
                </flux:button>
            @endif
            <flux:button icon="receipt-percent" variant="outline" href="{{ route('invoices.receipt', $invoice) }}"
                target="_blank">Receipt</flux:button>
            <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
            <flux:button icon="arrow-down-tray" variant="outline" wire:click="downloadPdf">PDF</flux:button>
        </div>
    </div>

    {{-- Flash --}}
    @if (session('success'))
        <div
            class="mb-4 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300 print:hidden">
            <flux:icon name="check-circle" class="size-4 shrink-0" />
            {{ session('success') }}
        </div>
    @endif

    {{-- Payment progress (screen only) --}}
    @if ($invoice->total > 0 && $invoice->status !== 'cancelled')
        @php
            $paidPct = min(100, round(($invoice->paid_amount / $invoice->total) * 100));
        @endphp
        <div
            class="mb-6 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 sm:grid-cols-3 dark:border-zinc-800 dark:bg-zinc-800 print:hidden">
            <div class="bg-white p-4 dark:bg-zinc-900">
                <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Invoice Total</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white">
                    {{ money($invoice->total) }}</div>
            </div>
            <div class="bg-white p-4 dark:bg-zinc-900">
                <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Paid</flux:text>
                <div class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-green-600 dark:text-green-400">
                    {{ money($invoice->paid_amount) }}</div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-green-500" style="width: {{ $paidPct }}%"></div>
                </div>
            </div>
            <div class="bg-white p-4 dark:bg-zinc-900">
                <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Balance Due</flux:text>
                <div
                    class="mt-1 text-2xl font-semibold tabular-nums tracking-tight {{ $invoice->due_amount > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400' }}">
                    {{ money($invoice->due_amount) }}</div>
                @if ($invoice->due_date && $invoice->due_amount > 0)
                    <flux:text class="mt-2 text-xs text-zinc-500">Due
                        {{ $invoice->due_date->format('d M Y') }}</flux:text>
                @endif
            </div>
        </div>
    @endif

    {{-- ============ PRINTABLE DOCUMENT ============ --}}
    <div id="invoice-print"
        class="mx-auto max-w-4xl rounded-xl border border-zinc-200 bg-white p-8 sm:p-10 dark:border-zinc-800 dark:bg-zinc-900 print:max-w-none print:rounded-none print:border-0 print:bg-white print:p-0">

        {{-- Company & Invoice Header --}}
        <div class="flex flex-wrap items-start justify-between gap-6">
            <div>
                @if ($company['company.logo'] ?? false)
                    <img src="{{ $company['company.logo'] }}" alt="" class="mb-3 h-10 w-auto" />
                @endif
                <div class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-white print:text-black">
                    {{ $company['company.name'] ?? config('app.name') }}
                </div>
                <div class="mt-1 space-y-0.5 text-sm text-zinc-500 print:text-zinc-600">
                    @if ($company['company.address'] ?? false)
                        <div>{{ $company['company.address'] }}</div>
                    @endif
                    @if ($company['company.phone'] ?? false)
                        <div>{{ $company['company.phone'] }}</div>
                    @endif
                    @if ($company['company.email'] ?? false)
                        <div>{{ $company['company.email'] }}</div>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <div
                    class="text-2xl font-semibold uppercase tracking-[0.2em] text-zinc-900 dark:text-white print:text-black">
                    Invoice</div>
                <div class="mt-1 font-mono text-sm text-zinc-500">{{ $invoice->invoice_number }}</div>

                <table class="mt-3 ml-auto text-sm">
                    <tr>
                        <td class="pr-4 text-left text-zinc-500">Issued</td>
                        <td class="text-right font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                            {{ $invoice->invoice_date->format('d M Y') }}</td>
                    </tr>
                    @if ($invoice->due_date)
                        <tr>
                            <td class="pr-4 text-left text-zinc-500">Due</td>
                            <td
                                class="text-right font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                                {{ $invoice->due_date->format('d M Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="pr-4 text-left text-zinc-500">Status</td>
                        <td class="text-right font-semibold uppercase text-zinc-900 dark:text-white print:text-black">
                            {{ $invoice->status }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Parties --}}
        <div class="mt-8 grid grid-cols-1 gap-6 border-t border-zinc-200 pt-6 sm:grid-cols-3 dark:border-zinc-800 print:border-zinc-300">
            <div>
                <div class="text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-400">Billed To</div>
                <div class="mt-2 font-medium text-zinc-900 dark:text-white print:text-black">
                    {{ $invoice->customer_name }}</div>
                <div class="mt-0.5 space-y-0.5 text-sm text-zinc-500">
                    @if ($invoice->customer_phone)
                        <div>{{ $invoice->customer_phone }}</div>
                    @endif
                    @if ($invoice->customer_email)
                        <div>{{ $invoice->customer_email }}</div>
                    @endif
                    @if ($invoice->customer_address)
                        <div>{{ $invoice->customer_address }}</div>
                    @endif
                </div>
            </div>
            <div>
                <div class="text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-400">Warehouse</div>
                <div class="mt-2 font-medium text-zinc-900 dark:text-white print:text-black">
                    {{ $invoice->warehouse?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-400">Served By</div>
                <div class="mt-2 font-medium text-zinc-900 dark:text-white print:text-black">
                    {{ $invoice->createdBy->name }}</div>
                <div class="mt-0.5 text-sm text-zinc-500">
                    {{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? '—')) }}</div>
            </div>
        </div>

        {{-- Items --}}
        <div class="mt-8 -mx-2 overflow-x-auto">
            <table class="w-full min-w-xl text-sm">
                <thead>
                    <tr
                        class="border-b border-zinc-300 text-[0.6875rem] uppercase tracking-wider text-zinc-500 dark:border-zinc-700">
                        <th class="w-8 px-2 py-2.5 text-left font-semibold">#</th>
                        <th class="px-2 py-2.5 text-left font-semibold">Item</th>
                        <th class="w-16 px-2 py-2.5 text-right font-semibold">Qty</th>
                        <th class="w-24 px-2 py-2.5 text-right font-semibold">Price</th>
                        <th class="w-24 px-2 py-2.5 text-right font-semibold">Disc.</th>
                        <th class="w-16 px-2 py-2.5 text-right font-semibold">Tax</th>
                        <th class="w-28 px-2 py-2.5 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $i => $item)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800 print:border-zinc-200">
                            <td class="px-2 py-3 tabular-nums text-zinc-400">{{ $i + 1 }}</td>
                            <td class="px-2 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white print:text-black">
                                    {{ $item->product_name }}</div>
                                <div class="mt-0.5 font-mono text-xs text-zinc-400">{{ $item->product_sku }}</div>
                            </td>
                            <td class="px-2 py-3 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-2 py-3 text-right tabular-nums text-zinc-500">{{ money($item->unit_price) }}
                            </td>
                            <td class="px-2 py-3 text-right tabular-nums text-zinc-500">
                                {{ $item->discount > 0 ? '−' . money($item->discount) : '—' }}</td>
                            <td class="px-2 py-3 text-right tabular-nums text-zinc-500">
                                {{ $item->tax_rate > 0 ? rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') . '%' : '—' }}
                            </td>
                            <td
                                class="px-2 py-3 text-right font-semibold tabular-nums text-zinc-900 dark:text-white print:text-black">
                                {{ money($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Subtotal</span>
                        <span class="font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                            {{ money($invoice->subtotal) }}</span>
                    </div>
                    @if ($invoice->discount > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Discount</span>
                            <span class="font-medium tabular-nums text-green-600 dark:text-green-400">
                                −{{ money($invoice->discount) }}</span>
                        </div>
                    @endif
                    @if ($invoice->tax > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Tax</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                                {{ money($invoice->tax) }}</span>
                        </div>
                    @endif
                    @if ($invoice->courier_charge > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Courier</span>
                            <span class="font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                                {{ money($invoice->courier_charge) }}</span>
                        </div>
                    @endif
                </div>

                <div
                    class="mt-3 flex items-baseline justify-between border-t-2 border-zinc-900 pt-3 dark:border-white print:border-black">
                    <span class="font-semibold uppercase tracking-wide text-zinc-900 dark:text-white print:text-black">
                        Total</span>
                    <span
                        class="text-xl font-semibold tabular-nums tracking-tight text-zinc-900 dark:text-white print:text-black">
                        {{ money($invoice->total) }}</span>
                </div>

                @if ($invoice->courier_cost > 0)
                    {{-- Internal only: what the courier cost us, and whether the
                         delivery charge covered it. Never printed. --}}
                    <div class="mt-3 space-y-1 border-t border-dashed border-zinc-200 pt-3 text-xs dark:border-zinc-700 print:hidden">
                        <div class="flex justify-between">
                            <span class="text-zinc-400">Courier cost (internal)</span>
                            <span class="tabular-nums text-zinc-500">{{ money($invoice->courier_cost) }}</span>
                        </div>
                        @if ($invoice->courier_margin < 0)
                            <div class="flex justify-between">
                                <span class="text-amber-600 dark:text-amber-400">Shop absorbed</span>
                                <span class="tabular-nums text-amber-600 dark:text-amber-400">
                                    {{ money(abs($invoice->courier_margin)) }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-3 space-y-2 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Paid</span>
                        <span class="font-medium tabular-nums text-green-600 dark:text-green-400">
                            {{ money($invoice->paid_amount) }}</span>
                    </div>
                    @if ($invoice->due_amount > 0)
                        <div class="flex justify-between text-base">
                            <span class="font-semibold text-red-600 dark:text-red-400">Balance Due</span>
                            <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">
                                {{ money($invoice->due_amount) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Payment History --}}
        @if ($invoice->payments->count() > 0)
            <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-800 print:border-zinc-300">
                <div class="text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-400">Payment History</div>
                <table class="mt-3 w-full text-sm">
                    <tbody>
                        @foreach ($invoice->payments as $payment)
                            <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                                <td class="py-2 pr-3 font-mono text-xs text-zinc-500">{{ $payment->payment_number }}</td>
                                <td class="py-2 pr-3">
                                    {{ ucfirst(str_replace('_', ' ', $payment->method)) }}
                                    @if ($payment->reference)
                                        <span class="text-zinc-400">· {{ $payment->reference }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 tabular-nums text-zinc-500">
                                    {{ $payment->payment_date->format('d M Y') }}</td>
                                <td
                                    class="py-2 text-right font-medium tabular-nums text-zinc-900 dark:text-white print:text-black">
                                    {{ money($payment->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Notes --}}
        @if ($invoice->notes)
            <div class="mt-8 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40 print:bg-transparent">
                <div class="text-[0.6875rem] font-semibold uppercase tracking-wider text-zinc-400">Notes</div>
                <div class="mt-1.5 text-sm text-zinc-600 dark:text-zinc-300 print:text-zinc-700">{{ $invoice->notes }}
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="mt-8 border-t border-zinc-200 pt-5 text-center dark:border-zinc-800 print:border-zinc-300">
            <div class="text-sm text-zinc-500">
                {{ $company['invoice.footer'] ?? 'Thank you for your business!' }}</div>
        </div>

    </div>

    {{-- ============ RECORD PAYMENT MODAL ============ --}}
    <flux:modal wire:model="showPaymentModal" class="w-full max-w-md print:hidden">
        <div>
            <div class="border-b border-zinc-200 px-6 pb-5 pt-6 text-center dark:border-zinc-800">
                <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500">Outstanding Balance
                </flux:text>
                <div class="mt-1.5 text-3xl font-semibold tabular-nums tracking-tight text-red-600 dark:text-red-400">
                    {{ money($invoice->due_amount) }}
                </div>
                <flux:text class="mt-1 font-mono text-xs text-zinc-500">{{ $invoice->invoice_number }}</flux:text>
            </div>

            <div class="space-y-4 p-6">
                <flux:field>
                    <flux:label badge="Required">Amount</flux:label>
                    <flux:input wire:model="payment_amount" type="number" step="0.01" min="0"
                        max="{{ $invoice->due_amount }}" prefix="৳"
                        class="h-12 text-lg font-semibold tabular-nums" />
                    <flux:error name="payment_amount" />
                </flux:field>

                <flux:field>
                    <flux:label>Payment Method</flux:label>
                    <flux:select wire:model="payment_method">
                        <flux:select.option value="cash">Cash</flux:select.option>
                        <flux:select.option value="card">Card</flux:select.option>
                        <flux:select.option value="bank_transfer">Bank Transfer</flux:select.option>
                        <flux:select.option value="cheque">Cheque</flux:select.option>
                        <flux:select.option value="other">Other</flux:select.option>
                    </flux:select>
                    <flux:error name="payment_method" />
                </flux:field>

                <flux:field>
                    <flux:label>Reference <flux:text class="text-xs text-zinc-400">(optional)</flux:text></flux:label>
                    <flux:input wire:model="payment_reference" placeholder="Transaction ID / cheque number" />
                    <flux:error name="payment_reference" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)" type="button">Cancel
                </flux:button>
                <flux:button wire:click="recordPayment" variant="primary" icon="check">Record Payment</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

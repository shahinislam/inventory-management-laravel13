<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between print:hidden">
        <div class="flex items-center gap-4">
            <flux:button icon="arrow-left" variant="ghost" href="{{ route('invoices.index') }}" wire:navigate />
            <div>
                <flux:heading size="xl">{{ $invoice->invoice_number }}</flux:heading>
                <flux:badge
                    size="sm"
                    :color="match($invoice->status) {
                        'paid' => 'green', 'partial' => 'yellow', 'overdue' => 'red',
                        'cancelled' => 'zinc', 'sent' => 'blue', default => 'zinc'
                    }"
                    class="mt-1"
                >{{ ucfirst($invoice->status) }}</flux:badge>
            </div>
        </div>

        <div class="flex gap-2">
            @if($invoice->due_amount > 0 && $invoice->status !== 'cancelled')
                <flux:button icon="banknotes" wire:click="openPaymentModal">Record Payment</flux:button>
            @endif
            <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
            <flux:button icon="arrow-down-tray" variant="outline" wire:click="downloadPdf">Download PDF</flux:button>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400 print:hidden">
            {{ session('success') }}
        </div>
    @endif

    {{-- Printable Invoice --}}
    <flux:card class="p-8 print:shadow-none print:border-none" id="invoice-print">

        {{-- Company & Invoice Header --}}
        <div class="flex items-start justify-between border-b border-zinc-200 pb-6 dark:border-zinc-700">
            <div>
                @if($company['logo'] ?? false)
                    <img src="{{ $company['logo'] }}" class="h-12 mb-2" />
                @endif
                <flux:heading size="lg">{{ $company['name'] ?? 'Company Name' }}</flux:heading>
                @if($company['address'] ?? false)
                    <flux:text class="text-sm text-zinc-500">{{ $company['address'] }}</flux:text>
                @endif
                @if($company['phone'] ?? false)
                    <flux:text class="text-sm text-zinc-500">{{ $company['phone'] }}</flux:text>
                @endif
                @if($company['email'] ?? false)
                    <flux:text class="text-sm text-zinc-500">{{ $company['email'] }}</flux:text>
                @endif
            </div>
            <div class="text-right">
                <flux:heading size="xl">INVOICE</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ $invoice->invoice_number }}</flux:text>
                <flux:text class="mt-2 text-sm">Date: {{ $invoice->invoice_date->format('d M Y') }}</flux:text>
                @if($invoice->due_date)
                    <flux:text class="text-sm">Due: {{ $invoice->due_date->format('d M Y') }}</flux:text>
                @endif
            </div>
        </div>

        {{-- Customer & Meta --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem" class="border-b border-zinc-200 py-6 dark:border-zinc-700">
            <div>
                <flux:text class="text-xs uppercase text-zinc-400">Billed To</flux:text>
                <flux:text class="mt-1 font-medium">{{ $invoice->customer_name }}</flux:text>
                @if($invoice->customer_phone)
                    <flux:text class="text-sm text-zinc-500">{{ $invoice->customer_phone }}</flux:text>
                @endif
                @if($invoice->customer_email)
                    <flux:text class="text-sm text-zinc-500">{{ $invoice->customer_email }}</flux:text>
                @endif
                @if($invoice->customer_address)
                    <flux:text class="text-sm text-zinc-500">{{ $invoice->customer_address }}</flux:text>
                @endif
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-400">Warehouse</flux:text>
                <flux:text class="mt-1 font-medium">{{ $invoice->warehouse?->name ?? '-' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-400">Served By</flux:text>
                <flux:text class="mt-1 font-medium">{{ $invoice->createdBy->name }}</flux:text>
                <flux:text class="text-sm text-zinc-500">Payment: {{ ucfirst(str_replace('_',' ', $invoice->payment_method ?? '-')) }}</flux:text>
            </div>
        </div>

        {{-- Items Table --}}
        <div class="py-6">
            <table style="width:100%;font-size:0.875rem">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-zinc-200, #e4e4e7)">
                        <th style="text-align:left;padding:0.5rem;font-weight:600">#</th>
                        <th style="text-align:left;padding:0.5rem;font-weight:600">Item</th>
                        <th style="text-align:right;padding:0.5rem;font-weight:600">Qty</th>
                        <th style="text-align:right;padding:0.5rem;font-weight:600">Price</th>
                        <th style="text-align:right;padding:0.5rem;font-weight:600">Discount</th>
                        <th style="text-align:right;padding:0.5rem;font-weight:600">Tax</th>
                        <th style="text-align:right;padding:0.5rem;font-weight:600">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $i => $item)
                        <tr style="border-bottom:1px solid var(--color-zinc-100, #f4f4f5)">
                            <td style="padding:0.5rem">{{ $i + 1 }}</td>
                            <td style="padding:0.5rem">
                                <flux:text class="font-medium">{{ $item->product_name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $item->product_sku }}</flux:text>
                            </td>
                            <td style="padding:0.5rem;text-align:right">{{ $item->quantity }}</td>
                            <td style="padding:0.5rem;text-align:right">{{ money($item->unit_price) }}</td>
                            <td style="padding:0.5rem;text-align:right">{{ $item->discount > 0 ? money($item->discount) : '-' }}</td>
                            <td style="padding:0.5rem;text-align:right">{{ $item->tax_rate > 0 ? $item->tax_rate.'%' : '-' }}</td>
                            <td style="padding:0.5rem;text-align:right">
                                <flux:text class="font-medium">{{ money($item->subtotal) }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="flex justify-end border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div style="width:280px" class="space-y-2">
                <div class="flex justify-between">
                    <flux:text class="text-sm text-zinc-500">Subtotal</flux:text>
                    <flux:text>{{ money($invoice->subtotal) }}</flux:text>
                </div>
                @if($invoice->discount > 0)
                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Discount</flux:text>
                        <flux:text class="text-green-500">-{{ money($invoice->discount) }}</flux:text>
                    </div>
                @endif
                @if($invoice->tax > 0)
                    <div class="flex justify-between">
                        <flux:text class="text-sm text-zinc-500">Tax</flux:text>
                        <flux:text>{{ money($invoice->tax) }}</flux:text>
                    </div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-700">
                    <flux:heading>Total</flux:heading>
                    <flux:heading>{{ money($invoice->total) }}</flux:heading>
                </div>
                <div class="flex justify-between">
                    <flux:text class="text-sm text-zinc-500">Paid</flux:text>
                    <flux:text class="text-green-500">{{ money($invoice->paid_amount) }}</flux:text>
                </div>
                @if($invoice->due_amount > 0)
                    <div class="flex justify-between">
                        <flux:heading class="text-red-500">Due</flux:heading>
                        <flux:heading class="text-red-500">{{ money($invoice->due_amount) }}</flux:heading>
                    </div>
                @endif
            </div>
        </div>

        {{-- Payment History --}}
        @if($invoice->payments->count() > 0)
            <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase text-zinc-400 mb-2">Payment History</flux:text>
                @foreach($invoice->payments as $payment)
                    <div class="flex justify-between text-sm py-1">
                        <span>{{ $payment->payment_number }} · {{ ucfirst(str_replace('_',' ',$payment->method)) }} · {{ $payment->payment_date->format('d M Y') }}</span>
                        <span class="font-medium">{{ money($payment->amount) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Notes & Footer --}}
        @if($invoice->notes)
            <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:text class="text-xs uppercase text-zinc-400">Notes</flux:text>
                <flux:text class="text-sm">{{ $invoice->notes }}</flux:text>
            </div>
        @endif

        @if($company['invoice.footer'] ?? false)
            <div class="mt-6 border-t border-zinc-200 pt-4 text-center dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-400">{{ $company['invoice.footer'] }}</flux:text>
            </div>
        @endif

    </flux:card>

    {{-- Record Payment Modal --}}
    <flux:modal wire:model="showPaymentModal" class="max-w-md print:hidden">
        <div class="p-6">
            <flux:heading class="mb-4">Record Payment</flux:heading>

            <div class="mb-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                <div class="flex justify-between text-sm">
                    <span>Total Due</span>
                    <span class="font-medium text-red-500">{{ money($invoice->due_amount) }}</span>
                </div>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Amount <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                    <flux:input wire:model="payment_amount" type="number" step="0.01" min="0" max="{{ $invoice->due_amount }}" prefix="৳" />
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
                    <flux:label>Reference</flux:label>
                    <flux:input wire:model="payment_reference" placeholder="Transaction ID / cheque number" />
                    <flux:error name="payment_reference" />
                </flux:field>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showPaymentModal', false)" type="button">Cancel</flux:button>
                <flux:button wire:click="recordPayment" icon="check">Record Payment</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

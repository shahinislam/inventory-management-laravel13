<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->invoice_number }}</title>
    @include("pdf.partials.thermal-styles")
</head>

<body>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print receipt</button>
    </div>

    {{-- Header --}}
    <div class="center">
        <div class="shop">{{ $company['company.name'] ?? config('app.name') }}</div>
        @if ($company['company.address'] ?? false)
            <div class="muted">{{ $company['company.address'] }}</div>
        @endif
        @if ($company['company.phone'] ?? false)
            <div class="muted">Tel: {{ $company['company.phone'] }}</div>
        @endif
        @if ($company['company.email'] ?? false)
            <div class="muted">{{ $company['company.email'] }}</div>
        @endif
    </div>

    <div class="rule-solid"></div>

    <div class="center bold" style="letter-spacing:1px">{{ $invoice->type === 'return' ? 'RETURN RECEIPT' : 'SALES RECEIPT' }}</div>

    <div class="rule"></div>

    {{-- Meta --}}
    <table class="meta">
        <tr>
            <td>Receipt</td>
            <td class="bold">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td>Date</td>
            <td>{{ $invoice->invoice_date->format('d/m/Y') }} {{ $invoice->created_at->format('H:i') }}</td>
        </tr>
        <tr>
            <td>Cashier</td>
            <td>{{ $invoice->createdBy->name }}</td>
        </tr>
        @if ($invoice->warehouse)
            <tr>
                <td>Outlet</td>
                <td>{{ $invoice->warehouse->name }}</td>
            </tr>
        @endif
        <tr>
            <td>Customer</td>
            <td>{{ $invoice->customer_name ?: 'Walk-in' }}</td>
        </tr>
        @if ($invoice->customer_phone)
            <tr>
                <td>Phone</td>
                <td>{{ $invoice->customer_phone }}</td>
            </tr>
        @endif
        @if ($invoice->member_no)
            <tr>
                <td>Member</td>
                <td>{{ $invoice->member_no }}</td>
            </tr>
        @endif
    </table>

    <div class="rule"></div>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="r" style="width:14mm">Qty</th>
                <th class="r" style="width:20mm">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td colspan="3" class="item-name bold">{{ $item->product_name }}@if ($item->is_gift) (Member gift)@endif</td>
                </tr>
                <tr>
                    <td class="muted">{{ money($item->unit_price, false) }}
                        @if ($item->discount > 0)
                            − {{ money($item->discount, false) }}
                        @endif
                        @if ($item->tax_rate > 0)
                            (+{{ rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') }}%)
                        @endif
                    </td>
                    <td class="r">× {{ format_qty($item->quantity) }}</td>
                    <td class="r bold">{{ money($item->subtotal, false) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rule"></div>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>{{ money($invoice->subtotal, false) }}</td>
        </tr>
        @if ($invoice->discount > 0)
            <tr>
                <td>Discount</td>
                <td>−{{ money($invoice->discount, false) }}</td>
            </tr>
        @endif
        @if ($invoice->membership_discount > 0)
            <tr>
                <td>Member reward</td>
                <td>−{{ money($invoice->membership_discount, false) }}</td>
            </tr>
        @endif
        @if ($invoice->tax > 0)
            <tr>
                <td>Tax</td>
                <td>{{ money($invoice->tax, false) }}</td>
            </tr>
        @endif
        @if ($invoice->courier_charge > 0)
            <tr>
                <td>Courier</td>
                <td>{{ money($invoice->courier_charge, false) }}</td>
            </tr>
        @endif
    </table>

    <div class="rule-solid"></div>

    <table class="totals grand">
        <tr>
            <td>TOTAL</td>
            <td>{{ money($invoice->total) }}</td>
        </tr>
    </table>

    <div class="rule-solid"></div>

    <table class="totals">
        <tr>
            <td>{{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? 'Paid')) }}@if ($account = $invoice->payments->first()?->paymentAccount)
                    ({{ $account->display_name }})
                @endif
            </td>
            <td>{{ money($invoice->paid_amount, false) }}</td>
        </tr>
        @if ($invoice->due_amount > 0)
            <tr class="bold">
                <td>Balance Due</td>
                <td>{{ money($invoice->due_amount, false) }}</td>
            </tr>
        @endif
    </table>

    @if ($invoice->notes)
        <div class="rule"></div>
        <div class="muted">{{ $invoice->notes }}</div>
    @endif

    <div class="rule"></div>

    {{-- Footer --}}
    <div class="center">
        <div class="thanks bold">{{ $company['invoice.footer'] ?? 'Thank you for your purchase!' }}</div>
        <div class="muted" style="margin-top:1.5mm">
            {{ $invoice->items->sum('quantity') }} {{ Str::plural('item', $invoice->items->sum('quantity')) }}
            · {{ $invoice->created_at->format('d M Y, H:i') }}
        </div>
        <div style="margin-top:2mm;display:flex;justify-content:center">
            {!! App\Services\Barcode\Code128::svg($invoice->invoice_number, 34, 1.2) !!}
        </div>
        <div class="muted" style="font-size:9px;letter-spacing:1px">{{ $invoice->invoice_number }}</div>
    </div>

    <div style="height:8mm"></div>

    <script>
        // Auto-open the print dialog when the POS opens this in a new tab.
        window.addEventListener('load', () => window.print());
    </script>

</body>

</html>

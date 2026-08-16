<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        /* DomPDF has no flexbox and only partial float support, so the layout
           below leans on tables and explicit widths throughout. */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 14mm 14mm 18mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #27272a;
        }

        .muted {
            color: #71717a;
        }

        .faint {
            color: #a1a1aa;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        /* --- Header ------------------------------------------------------ */

        .head {
            width: 100%;
        }

        .head td {
            vertical-align: top;
        }

        .company-name {
            font-size: 15pt;
            font-weight: bold;
            color: #18181b;
        }

        .invoice-word {
            font-size: 20pt;
            font-weight: bold;
            letter-spacing: 3pt;
            color: #18181b;
            text-transform: uppercase;
        }

        .invoice-no {
            font-size: 10pt;
            color: #71717a;
            margin-top: 2pt;
        }

        .head-meta {
            margin-top: 8pt;
            font-size: 9.5pt;
        }

        .head-meta td {
            padding: 1pt 0;
        }

        .head-meta .k {
            color: #71717a;
            padding-right: 12pt;
        }

        /* --- Section labels --------------------------------------------- */

        .label {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1pt;
            color: #a1a1aa;
            padding-bottom: 3pt;
        }

        .rule {
            border-top: 1px solid #e4e4e7;
            height: 1px;
            font-size: 0;
            line-height: 0;
        }

        /* --- Parties ---------------------------------------------------- */

        .parties {
            width: 100%;
            margin-top: 14pt;
        }

        .parties td {
            vertical-align: top;
            width: 33.33%;
            padding-right: 10pt;
        }

        /* --- Items ------------------------------------------------------ */

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16pt;
        }

        .items thead th {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #52525b;
            text-align: left;
            padding: 5pt 4pt;
            border-bottom: 1.5px solid #18181b;
        }

        .items th.r,
        .items td.r {
            text-align: right;
        }

        .items tbody td {
            padding: 6pt 4pt;
            border-bottom: 1px solid #f4f4f5;
            font-size: 10pt;
        }

        .sku {
            font-size: 8pt;
            color: #a1a1aa;
        }

        /* --- Totals ----------------------------------------------------- */

        .totals-wrap {
            width: 100%;
            margin-top: 12pt;
        }

        .totals {
            width: 62mm;
            border-collapse: collapse;
        }

        .totals td {
            padding: 2.5pt 0;
            font-size: 10pt;
        }

        .totals td:last-child {
            text-align: right;
        }

        .totals .grand td {
            border-top: 1.5px solid #18181b;
            padding-top: 5pt;
            font-size: 13pt;
            font-weight: bold;
            color: #18181b;
        }

        .totals .sep td {
            border-top: 1px solid #e4e4e7;
            padding-top: 5pt;
        }

        .green {
            color: #15803d;
        }

        .red {
            color: #b91c1c;
        }

        /* --- Badge ------------------------------------------------------ */

        .badge {
            display: inline-block;
            padding: 2pt 6pt;
            border-radius: 3pt;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
        }

        .badge-paid {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-partial {
            background: #fef9c3;
            color: #a16207;
        }

        .badge-due {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* --- Blocks ----------------------------------------------------- */

        .payments {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6pt;
        }

        .payments td {
            padding: 3pt 0;
            border-bottom: 1px solid #f4f4f5;
            font-size: 9.5pt;
        }

        .notes {
            margin-top: 16pt;
            padding: 8pt 10pt;
            border: 1px solid #e4e4e7;
            border-radius: 3pt;
            font-size: 9.5pt;
        }

        .footer {
            margin-top: 20pt;
            border-top: 1px solid #e4e4e7;
            padding-top: 10pt;
            text-align: center;
            color: #a1a1aa;
            font-size: 9pt;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <table class="head">
        <tr>
            <td style="width:55%">
                <div class="company-name">{{ $company['company.name'] ?? config('app.name') }}</div>
                @if ($company['company.address'] ?? false)
                    <div class="muted" style="margin-top:3pt">{{ $company['company.address'] }}</div>
                @endif
                @if ($company['company.phone'] ?? false)
                    <div class="muted">{{ $company['company.phone'] }}</div>
                @endif
                @if ($company['company.email'] ?? false)
                    <div class="muted">{{ $company['company.email'] }}</div>
                @endif
            </td>
            <td class="right">
                <div class="invoice-word">Invoice</div>
                <div class="invoice-no">{{ $invoice->invoice_number }}</div>

                <table class="head-meta" style="margin-left:auto">
                    <tr>
                        <td class="k">Issued</td>
                        <td class="right bold">{{ $invoice->invoice_date->format('d M Y') }}</td>
                    </tr>
                    @if ($invoice->due_date)
                        <tr>
                            <td class="k">Due</td>
                            <td class="right bold">{{ $invoice->due_date->format('d M Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Status</td>
                        <td class="right">
                            <span
                                class="badge {{ $invoice->status === 'paid' ? 'badge-paid' : ($invoice->status === 'partial' ? 'badge-partial' : 'badge-due') }}">
                                {{ strtoupper($invoice->status) }}
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="height:14pt"></div>
    <div class="rule"></div>

    {{-- Parties --}}
    <table class="parties">
        <tr>
            <td>
                <div class="label">Billed To</div>
                <div class="bold">{{ $invoice->customer_name }}</div>
                @if ($invoice->customer_phone)
                    <div class="muted">{{ $invoice->customer_phone }}</div>
                @endif
                @if ($invoice->customer_email)
                    <div class="muted">{{ $invoice->customer_email }}</div>
                @endif
                @if ($invoice->customer_address)
                    <div class="muted">{{ $invoice->customer_address }}</div>
                @endif
            </td>
            <td>
                <div class="label">Payment Method</div>
                <div class="bold">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? '—')) }}</div>
            </td>
            <td>
                <div class="label">Served By</div>
                <div class="bold">{{ $invoice->createdBy->name }}</div>
                @if ($invoice->warehouse)
                    <div class="muted">{{ $invoice->warehouse->name }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th>Item</th>
                <th class="r" style="width:9%">Qty</th>
                <th class="r" style="width:14%">Price</th>
                <th class="r" style="width:13%">Disc.</th>
                <th class="r" style="width:9%">Tax</th>
                <th class="r" style="width:16%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td class="faint">{{ $i + 1 }}</td>
                    <td>
                        <span class="bold">{{ $item->product_name }}</span>
                        <div class="sku">{{ $item->product_sku }}</div>
                    </td>
                    <td class="r">{{ $item->quantity }}</td>
                    <td class="r muted">{{ money($item->unit_price) }}</td>
                    <td class="r muted">{{ $item->discount > 0 ? '−' . money($item->discount) : '—' }}</td>
                    <td class="r muted">
                        {{ $item->tax_rate > 0 ? rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') . '%' : '—' }}
                    </td>
                    <td class="r bold">{{ money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals (right-aligned via an empty spacer cell) --}}
    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width:62mm">
                <table class="totals">
                    <tr>
                        <td class="muted">Subtotal</td>
                        <td>{{ money($invoice->subtotal) }}</td>
                    </tr>
                    @if ($invoice->discount > 0)
                        <tr>
                            <td class="muted">Discount</td>
                            <td class="green">−{{ money($invoice->discount) }}</td>
                        </tr>
                    @endif
                    @if ($invoice->tax > 0)
                        <tr>
                            <td class="muted">Tax</td>
                            <td>{{ money($invoice->tax) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>TOTAL</td>
                        <td>{{ money($invoice->total) }}</td>
                    </tr>
                    <tr class="sep">
                        <td class="muted">Paid</td>
                        <td class="green">{{ money($invoice->paid_amount) }}</td>
                    </tr>
                    @if ($invoice->due_amount > 0)
                        <tr>
                            <td class="red bold">Balance Due</td>
                            <td class="red bold">{{ money($invoice->due_amount) }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Payment history --}}
    @if ($invoice->payments->count() > 0)
        <div style="margin-top:18pt">
            <div class="label">Payment History</div>
            <table class="payments">
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td class="muted" style="width:28%">{{ $payment->payment_number }}</td>
                        <td style="width:26%">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}@if ($payment->reference)
                                <span class="faint">· {{ $payment->reference }}</span>
                            @endif
                        </td>
                        <td class="muted" style="width:26%">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="right bold">{{ money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Notes --}}
    @if ($invoice->notes)
        <div class="notes">
            <div class="label">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        {{ $company['invoice.footer'] ?? 'Thank you for your business!' }}
    </div>

</body>

</html>

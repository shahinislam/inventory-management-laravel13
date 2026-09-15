<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $invoice->invoice_number }}</title>
    <style>
        /* 80mm thermal roll. Printable width is ~72mm once the printer's
           unprintable margin is accounted for. */
        @page {
            size: 80mm auto;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
            background: #fff;
            color: #000;
            /* Monospace keeps the amount column aligned on a fixed-pitch
               thermal head. */
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.45;
            -webkit-font-smoothing: none;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .shop {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .muted {
            font-size: 10px;
        }

        /* Dashed rules read as torn perforations and survive low-res heads
           better than hairlines. */
        .rule {
            border-top: 1px dashed #000;
            margin: 2.5mm 0;
        }

        .rule-solid {
            border-top: 2px solid #000;
            margin: 2mm 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            font-size: 10px;
            padding: 0.3mm 0;
            vertical-align: top;
        }

        .meta td:last-child {
            text-align: right;
        }

        .items th {
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
            padding: 1mm 0;
            border-bottom: 1px dashed #000;
        }

        .items th.r,
        .items td.r {
            text-align: right;
        }

        .items td {
            padding: 1mm 0 0;
            vertical-align: top;
        }

        /* The product name spans the full width on its own line so long
           names are never truncated; qty x price sits underneath. */
        .item-name {
            padding-top: 2mm;
            word-break: break-word;
        }

        .totals td {
            padding: 0.4mm 0;
        }

        .totals td:last-child {
            text-align: right;
        }

        .grand td {
            font-size: 14px;
            font-weight: bold;
            padding: 1.5mm 0;
        }

        .thanks {
            font-size: 11px;
        }

        .barcode {
            margin-top: 2mm;
            font-family: 'Libre Barcode 39', monospace;
            font-size: 10px;
            letter-spacing: 2px;
        }

        @media print {
            body {
                padding: 2mm 4mm;
            }

            .no-print {
                display: none !important;
            }
        }

        /* On-screen preview: centre the roll on a grey desk so it reads as
           a physical receipt before printing. */
        @media screen {
            html {
                background: #52525b;
                padding: 24px 0;
                min-height: 100%;
            }

            body {
                box-shadow: 0 8px 24px rgba(0, 0, 0, .35);
            }

            .no-print {
                width: 80mm;
                margin: 0 auto 12px;
                text-align: center;
                font-family: ui-sans-serif, system-ui, sans-serif;
            }

            .no-print button {
                cursor: pointer;
                border: 0;
                border-radius: 6px;
                background: #fff;
                color: #18181b;
                padding: 7px 14px;
                font-size: 13px;
                font-weight: 600;
                font-family: inherit;
            }
        }
    </style>
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

    <div class="center bold" style="letter-spacing:1px">SALES RECEIPT</div>

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
                    <td colspan="3" class="item-name bold">{{ $item->product_name }}</td>
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
                    <td class="r">× {{ $item->quantity }}</td>
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
            <td>{{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? 'Paid')) }}</td>
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
        <div class="barcode">*{{ $invoice->invoice_number }}*</div>
    </div>

    <div style="height:8mm"></div>

    <script>
        // Auto-open the print dialog when the POS opens this in a new tab.
        window.addEventListener('load', () => window.print());
    </script>

</body>

</html>

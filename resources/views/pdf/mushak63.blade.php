@php
    $isReturn = $invoice->type === 'return';
    $formTitle = $isReturn ? 'Credit Note' : 'Tax Invoice';
    $formNo = $isReturn ? 'Mushak-6.7' : 'Mushak-6.3';
    $rate = fn ($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.').'%';

    $lines = $invoice->items->values()->map(function ($item, $i) {
        $qty = (float) $item->quantity;
        $value = round(((float) $item->unit_price - (float) $item->discount) * $qty, 2);
        $vat = round($value * (float) $item->tax_rate / 100, 2);

        return [
            'sl' => $i + 1,
            'name' => $item->product_name.($item->is_gift ? ' (gift)' : ''),
            'unit' => $item->product?->unit ?: 'pcs',
            'qty' => $qty,
            'unit_price' => (float) $item->unit_price - (float) $item->discount,
            'value' => $value,
            'vat_rate' => (float) $item->tax_rate,
            'vat' => $vat,
            'total' => round($value + $vat, 2),
        ];
    });

    $totalValue = $lines->sum('value');
    $totalVat = $lines->sum('vat');
    $totalAll = $lines->sum('total');
    $buyerBin = $invoice->customer?->tax_number;
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $formNo }} {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4; margin: 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0 auto;
            max-width: 190mm;
            padding: 8mm 0;
        }

        .no-print { text-align: center; margin-bottom: 6mm; }

        .no-print button {
            padding: 8px 20px;
            font-size: 14px;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
            background: #111827;
            color: #fff;
        }

        .head { text-align: center; position: relative; margin-bottom: 5mm; }
        .head .gov { font-size: 10pt; }
        .head .nbr { font-size: 11pt; font-weight: bold; }
        .head .title { font-size: 13pt; font-weight: bold; margin-top: 2mm; text-transform: uppercase; }
        .head .rule { font-size: 8.5pt; }
        .form-no {
            position: absolute;
            top: 0;
            right: 0;
            border: 1px solid #000;
            padding: 1mm 3mm;
            font-weight: bold;
        }

        .parties { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .parties td { vertical-align: top; padding: 0.8mm 0; }
        .parties .lbl { width: 42mm; color: #333; }
        .warn { color: #b91c1c; font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.items th, table.items td { border: 1px solid #000; padding: 1.2mm 1.4mm; }
        table.items th { background: #f3f4f6; font-weight: bold; text-align: center; }
        table.items td.r { text-align: right; white-space: nowrap; }
        table.items td.c { text-align: center; }
        table.items tr.total td { font-weight: bold; }
        .colno td { text-align: center; font-size: 7.5pt; color: #444; padding: 0.6mm; }

        table.summary { margin-left: auto; margin-top: 3mm; border-collapse: collapse; min-width: 75mm; }
        table.summary td { padding: 1mm 2mm; }
        table.summary td.r { text-align: right; }
        table.summary tr.grand td { border-top: 1.5px solid #000; font-weight: bold; font-size: 11pt; }

        .sign { margin-top: 18mm; display: flex; justify-content: flex-end; }
        .sign div { width: 80mm; border-top: 1px solid #000; padding-top: 1.5mm; text-align: center; font-size: 9pt; }

        .foot { margin-top: 6mm; font-size: 8pt; color: #444; }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            table.items th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print {{ $formNo }}</button>
    </div>

    <div class="head">
        <div class="form-no">{{ $formNo }}</div>
        <div class="gov">Government of the People's Republic of Bangladesh</div>
        <div class="nbr">National Board of Revenue</div>
        <div class="title">{{ $formTitle }}</div>
        @unless ($isReturn)
            <div class="rule">[See clause (c) and (f) of Sub-rule (1) of Rule 40]</div>
        @endunless
    </div>

    <table class="parties">
        <tr>
            <td class="lbl">Name of registered person:</td>
            <td><strong>{{ $company['company.name'] ?? config('app.name') }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">BIN of registered person:</td>
            <td>
                @if ($bin)
                    <strong>{{ $bin }}</strong>
                @else
                    <span class="warn">BIN not set — add it in Settings</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Address of issuing point:</td>
            <td>{{ $company['company.address'] ?? '—' }}@if ($invoice->warehouse) ({{ $invoice->warehouse->name }})@endif</td>
        </tr>
        <tr><td colspan="2" style="height:2mm"></td></tr>
        <tr>
            <td class="lbl">Name of buyer:</td>
            <td>{{ $invoice->customer_name ?: 'Walk-in customer' }}</td>
        </tr>
        <tr>
            <td class="lbl">BIN of buyer:</td>
            <td>{{ $buyerBin ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Address of buyer:</td>
            <td>{{ $invoice->customer_address ?: '—' }}</td>
        </tr>
        @if ($invoice->customer_phone)
            <tr>
                <td class="lbl">Phone of buyer:</td>
                <td>{{ $invoice->customer_phone }}</td>
            </tr>
        @endif
        <tr><td colspan="2" style="height:2mm"></td></tr>
        <tr>
            <td class="lbl">{{ $isReturn ? 'Credit note no:' : 'Challan / Invoice no:' }}</td>
            <td><strong>{{ $invoice->invoice_number }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">Date of issue:</td>
            <td>{{ $invoice->invoice_date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="lbl">Time of issue:</td>
            <td>{{ $invoice->created_at?->format('h:i A') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Sl</th>
                <th>Description of goods / services</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Unit price (excl. VAT)</th>
                <th>Total value (excl. VAT)</th>
                <th>SD rate</th>
                <th>SD amount</th>
                <th>VAT rate</th>
                <th>VAT amount</th>
                <th>Total incl. all taxes</th>
            </tr>
            <tr class="colno">
                @foreach (range(1, 11) as $n)
                    <td>({{ $n }})</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td class="c">{{ $line['sl'] }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="c">{{ $line['unit'] }}</td>
                    <td class="r">{{ format_qty($line['qty']) }}</td>
                    <td class="r">{{ money($line['unit_price'], false) }}</td>
                    <td class="r">{{ money($line['value'], false) }}</td>
                    <td class="c">0%</td>
                    <td class="r">{{ money(0, false) }}</td>
                    <td class="c">{{ $rate($line['vat_rate']) }}</td>
                    <td class="r">{{ money($line['vat'], false) }}</td>
                    <td class="r">{{ money($line['total'], false) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="c">No items.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="5" class="r">Total</td>
                <td class="r">{{ money($totalValue, false) }}</td>
                <td></td>
                <td class="r">{{ money(0, false) }}</td>
                <td></td>
                <td class="r">{{ money($totalVat, false) }}</td>
                <td class="r">{{ money($totalAll, false) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        @if ((float) $invoice->discount > 0)
            <tr>
                <td>Bill discount</td>
                <td class="r">−{{ money($invoice->discount, false) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->membership_discount > 0)
            <tr>
                <td>Member discount</td>
                <td class="r">−{{ money($invoice->membership_discount, false) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->courier_charge > 0)
            <tr>
                <td>Delivery / courier charge</td>
                <td class="r">{{ money($invoice->courier_charge, false) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>{{ $isReturn ? 'Total credited' : 'Grand total' }}</td>
            <td class="r">{{ money($invoice->total) }}</td>
        </tr>
    </table>

    <div class="sign">
        <div>
            Name, designation and signature of authorised person
            @if ($invoice->createdBy)
                <br><strong>{{ $invoice->createdBy->name }}</strong>
            @endif
        </div>
    </div>

    <div class="foot">
        * SD = Supplementary Duty. Values are shown in {{ App\Models\Setting::get('currency.code', 'BDT') }}.
    </div>

    <script>
        window.addEventListener('load', () => window.print());
    </script>

</body>

</html>

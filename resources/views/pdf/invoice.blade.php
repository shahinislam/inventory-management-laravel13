<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #333; padding: 30px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .company-name { font-size: 22px; font-weight: bold; }
        .invoice-title { font-size: 28px; font-weight: bold; text-align: right; color: #333; }
        .invoice-number { font-size: 14px; color: #666; text-align: right; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .meta-box { width: 30%; }
        .meta-label { font-size: 10px; text-transform: uppercase; color: #999; margin-bottom: 4px; }
        .meta-value { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        thead tr { background: #333; color: white; }
        th { padding: 8px 10px; text-align: left; font-size: 12px; }
        th.right, td.right { text-align: right; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
        .totals { display: flex; justify-content: flex-end; }
        .totals-box { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
        .totals-total { font-weight: bold; font-size: 15px; border-top: 2px solid #333; margin-top: 6px; padding-top: 6px; }
        .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px; text-align: center; color: #999; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-due { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div>
            <div class="company-name">{{ $company['company.name'] ?? 'Company Name' }}</div>
            @if($company['company.address'] ?? false)
                <div style="margin-top:4px;color:#666">{{ $company['company.address'] }}</div>
            @endif
            @if($company['company.phone'] ?? false)
                <div style="color:#666">{{ $company['company.phone'] }}</div>
            @endif
            @if($company['company.email'] ?? false)
                <div style="color:#666">{{ $company['company.email'] }}</div>
            @endif
        </div>
        <div>
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            <div style="margin-top:8px;font-size:12px;text-align:right">
                Date: {{ $invoice->invoice_date->format('d M Y') }}<br>
                @if($invoice->due_date) Due: {{ $invoice->due_date->format('d M Y') }} @endif
            </div>
        </div>
    </div>

    {{-- Meta --}}
    <div class="meta">
        <div class="meta-box">
            <div class="meta-label">Billed To</div>
            <div class="meta-value">{{ $invoice->customer_name }}</div>
            @if($invoice->customer_phone)<div>{{ $invoice->customer_phone }}</div>@endif
            @if($invoice->customer_email)<div>{{ $invoice->customer_email }}</div>@endif
            @if($invoice->customer_address)<div>{{ $invoice->customer_address }}</div>@endif
        </div>
        <div class="meta-box">
            <div class="meta-label">Payment</div>
            <div class="meta-value">{{ ucfirst(str_replace('_',' ', $invoice->payment_method ?? '-')) }}</div>
            <div style="margin-top:8px">
                <span class="badge {{ $invoice->status === 'paid' ? 'badge-paid' : ($invoice->status === 'partial' ? 'badge-partial' : 'badge-due') }}">
                    {{ strtoupper($invoice->status) }}
                </span>
            </div>
        </div>
        <div class="meta-box">
            <div class="meta-label">Served By</div>
            <div class="meta-value">{{ $invoice->createdBy->name }}</div>
            @if($invoice->warehouse)
                <div style="margin-top:4px;color:#666">{{ $invoice->warehouse->name }}</div>
            @endif
        </div>
    </div>

    {{-- Items --}}
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>SKU</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Discount</th>
                <th class="right">Tax</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->product_sku }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ money($item->unit_price) }}</td>
                    <td class="right">{{ $item->discount > 0 ? money($item->discount) : '-' }}</td>
                    <td class="right">{{ $item->tax_rate > 0 ? $item->tax_rate.'%' : '-' }}</td>
                    <td class="right">{{ money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals">
        <div class="totals-box">
            <div class="totals-row"><span>Subtotal</span><span>{{ money($invoice->subtotal) }}</span></div>
            @if($invoice->discount > 0)
                <div class="totals-row"><span>Discount</span><span>-{{ money($invoice->discount) }}</span></div>
            @endif
            @if($invoice->tax > 0)
                <div class="totals-row"><span>Tax</span><span>{{ money($invoice->tax) }}</span></div>
            @endif
            <div class="totals-row totals-total"><span>Total</span><span>{{ money($invoice->total) }}</span></div>
            <div class="totals-row" style="color:#059669"><span>Paid</span><span>{{ money($invoice->paid_amount) }}</span></div>
            @if($invoice->due_amount > 0)
                <div class="totals-row" style="color:#dc2626;font-weight:bold"><span>Due</span><span>{{ money($invoice->due_amount) }}</span></div>
            @endif
        </div>
    </div>

    {{-- Notes --}}
    @if($invoice->notes)
        <div style="margin-top:20px;padding:10px;background:#f9f9f9;border-radius:4px">
            <strong>Notes:</strong> {{ $invoice->notes }}
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        {{ $company['invoice.footer'] ?? 'Thank you for your business!' }}
    </div>

</body>
</html>

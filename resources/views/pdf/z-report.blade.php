<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Z-report {{ $shift->shift_number }}</title>
    @include('pdf.partials.thermal-styles')
</head>

<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print Z-report</button>
    </div>

    <div class="center">
        <div class="shop">{{ $company['company.name'] ?? config('app.name') }}</div>
        @if ($shift->warehouse)
            <div class="muted">{{ $shift->warehouse->name }}</div>
        @endif
    </div>

    <div class="rule-solid"></div>
    <div class="center bold" style="letter-spacing:1px">SHIFT REPORT (Z)</div>
    <div class="rule"></div>

    <table class="meta">
        <tr><td>Shift</td><td class="bold">{{ $shift->shift_number }}</td></tr>
        <tr><td>Cashier</td><td>{{ $shift->user->name }}</td></tr>
        <tr><td>Opened</td><td>{{ $shift->opened_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Closed</td><td>{{ $shift->closed_at?->format('d/m/Y H:i') ?? 'still open' }}</td></tr>
        @if ($shift->closedBy && $shift->closed_by !== $shift->user_id)
            <tr><td>Closed by</td><td>{{ $shift->closedBy->name }}</td></tr>
        @endif
    </table>

    <div class="rule"></div>

    <table class="totals">
        <tr><td>Sales</td><td>{{ $summary['sales_count'] }} / {{ money($summary['sales_total'], false) }}</td></tr>
        <tr><td>Given on due</td><td>{{ money($summary['due_given'], false) }}</td></tr>
        <tr><td>Returns</td><td>{{ $summary['returns_count'] }} / {{ money($summary['returns_total'], false) }}</td></tr>
    </table>

    <div class="rule"></div>
    <div class="bold">TAKINGS BY METHOD</div>
    <table class="totals">
        @forelse ($summary['by_method'] as $row)
            <tr><td>{{ $row['label'] }} ({{ $row['count'] }})</td><td>{{ money($row['total'], false) }}</td></tr>
            @if ($row['refunded'] > 0)
                <tr><td>&nbsp; refunded</td><td>−{{ money($row['refunded'], false) }}</td></tr>
            @endif
        @empty
            <tr><td>No payments</td><td>0</td></tr>
        @endforelse
    </table>

    <div class="rule"></div>
    <div class="bold">CASH DRAWER</div>
    <table class="totals">
        <tr><td>Opening cash</td><td>{{ money($summary['opening_cash'], false) }}</td></tr>
        <tr><td>+ Cash sales</td><td>{{ money($summary['cash_sales'], false) }}</td></tr>
        <tr><td>− Cash refunds</td><td>{{ money($summary['cash_refunds'], false) }}</td></tr>
        <tr><td>+ Cash added</td><td>{{ money($summary['cash_in'], false) }}</td></tr>
        <tr><td>− Cash taken out</td><td>{{ money($summary['cash_out'], false) }}</td></tr>
        <tr><td>− Expenses</td><td>{{ money($summary['cash_expenses'], false) }}</td></tr>
    </table>
    <div class="rule-solid"></div>
    <table class="totals grand">
        <tr><td>EXPECTED</td><td>{{ money($summary['expected_cash'], false) }}</td></tr>
    </table>
    @if ($shift->counted_cash !== null)
        <table class="totals">
            <tr><td>Counted</td><td>{{ money($shift->counted_cash, false) }}</td></tr>
            <tr class="bold"><td>{{ (float) $shift->difference >= 0 ? 'Over' : 'Short' }}</td><td>{{ money(abs((float) $shift->difference), false) }}</td></tr>
        </table>
    @endif

    <div class="rule"></div>
    <div class="center muted">Printed {{ now()->format('d/m/Y H:i') }}</div>
    <br><br>
    <div class="muted">Cashier sign: ____________</div>
    <br>
    <div class="muted">Manager sign: ____________</div>

    <script>window.addEventListener('load', () => window.print());</script>
</body>

</html>

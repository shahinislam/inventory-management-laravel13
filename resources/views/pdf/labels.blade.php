@php
    $size = in_array($size ?? '', ['38x25', '50x30', 'a4'], true) ? $size : '38x25';
    $shop = App\Models\Setting::get('company.name') ?: config('app.name');

    $dims = [
        '38x25' => ['w' => 38, 'h' => 25, 'bar' => 7],
        '50x30' => ['w' => 50, 'h' => 30, 'bar' => 9],
        'a4' => ['w' => 70, 'h' => 37.125, 'bar' => 10],
    ][$size];

    // Expand copies into a flat list of labels.
    $labels = collect($items)->flatMap(function ($row) {
        $product = $row['product'];
        $copies = max(1, min(500, (int) ($row['copies'] ?? 1)));
        $weight = $product->isLoose() && (float) ($row['weight'] ?? 0) > 0 ? (float) $row['weight'] : null;
        $code = $product->barcode ?: $product->sku;

        $label = [
            'name' => $product->name,
            'price' => (float) $product->selling_price,
            'unit' => $product->unit,
            'loose' => $product->isLoose(),
            'weight' => $weight,
            'weightPrice' => $weight !== null ? round($weight * (float) $product->selling_price, 2) : null,
            'code' => (string) $code,
            'svg' => $code ? \App\Services\Barcode\Code128::svg((string) $code, 40, 1) : '',
        ];

        return array_fill(0, $copies, $label);
    });
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Price labels</title>
    <style>
        @page {
            @if ($size === 'a4')
                size: 210mm 297mm;
            @else
                size: {{ $dims['w'] }}mm {{ $dims['h'] }}mm;
            @endif
            margin: 0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #e5e7eb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print {
            padding: 12px;
            text-align: center;
            font-size: 14px;
        }

        .no-print button {
            padding: 8px 20px;
            font-size: 14px;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
            background: #111827;
            color: #fff;
        }

        .no-print .hint { margin-top: 6px; color: #4b5563; font-size: 12px; }

        .label {
            width: {{ $dims['w'] }}mm;
            height: {{ $dims['h'] }}mm;
            padding: 1.2mm 1.6mm;
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .shop {
            font-size: {{ $size === '38x25' ? '5.5pt' : '6.5pt' }};
            text-transform: uppercase;
            letter-spacing: .3px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .name {
            font-size: {{ $size === '38x25' ? '7pt' : '8.5pt' }};
            font-weight: bold;
            line-height: 1.15;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .price {
            font-size: {{ $size === '38x25' ? '11pt' : '14pt' }};
            font-weight: bold;
            line-height: 1.1;
        }

        .price small { font-size: 6pt; font-weight: normal; }

        .weight { font-size: {{ $size === '38x25' ? '6pt' : '7pt' }}; font-weight: bold; }

        .barcode svg {
            display: block;
            width: 100%;
            height: {{ $dims['bar'] }}mm;
        }

        .code { font-family: 'Courier New', monospace; font-size: 6pt; letter-spacing: .5px; }

        @if ($size === 'a4')
            .sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto 10px;
                background: #fff;
                display: grid;
                grid-template-columns: repeat(3, 70mm);
                grid-auto-rows: 37.125mm;
                page-break-after: always;
                break-after: page;
            }

            .sheet .label { outline: 0.2mm dashed #d1d5db; }
        @else
            .label {
                margin: 0 auto 6px;
                page-break-after: always;
                break-after: page;
            }
        @endif

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .label { margin: 0; outline: none !important; }
            .sheet { margin: 0; }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
        <div class="hint">
            {{ $labels->count() }} {{ Str::plural('label', $labels->count()) }} ·
            {{ $size === 'a4' ? 'A4 sheet, 3 × 8' : $dims['w'].' × '.$dims['h'].' mm' }}
            · set the printer margins to "None" and scale to 100%.
        </div>
    </div>

    @if ($labels->isEmpty())
        <div class="no-print">No products selected.</div>
    @endif

    @php $chunks = $size === 'a4' ? $labels->chunk(24) : collect([$labels]); @endphp

    @foreach ($chunks as $chunk)
        @if ($size === 'a4')<div class="sheet">@endif

        @foreach ($chunk as $label)
            <div class="label">
                <div class="shop">{{ $shop }}</div>
                <div class="name">{{ $label['name'] }}</div>

                @if ($label['weight'] !== null)
                    <div class="price">{{ money($label['weightPrice']) }}</div>
                    <div class="weight">{{ format_qty($label['weight'], $label['unit']) }} × {{ money($label['price']) }}/{{ $label['unit'] }}</div>
                @else
                    <div class="price">
                        {{ money($label['price']) }}@if ($label['loose'])<small> per {{ $label['unit'] }}</small>@endif
                    </div>
                @endif

                @if ($label['svg'])
                    <div class="barcode">{!! $label['svg'] !!}</div>
                    <div class="code">{{ $label['code'] }}</div>
                @endif
            </div>
        @endforeach

        @if ($size === 'a4')</div>@endif
    @endforeach

    <script>
        window.addEventListener('load', () => window.print());
    </script>

</body>

</html>

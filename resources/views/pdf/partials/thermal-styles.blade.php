{{-- 80mm thermal roll styles shared by the receipt, return receipt and Z-report. --}}
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

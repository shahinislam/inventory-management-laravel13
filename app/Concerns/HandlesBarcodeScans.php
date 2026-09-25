<?php

namespace App\Concerns;

use App\Models\Product;
use Livewire\Attributes\On;

/**
 * Receives scans from a plug-and-play barcode scanner (see resources/js/app.js)
 * and reports back so the browser can beep and show a not-found message.
 *
 * The component implements handleScan(): return true when the scan was used.
 */
trait HandlesBarcodeScans
{
    /** Set by handleScan() to replace the default not-found message. */
    protected ?string $scanError = null;

    abstract protected function handleScan(string $code): bool;

    #[On('barcode-scanned')]
    public function onBarcodeScanned(string $code): void
    {
        $code = trim($code);
        $found = $code !== '' && $this->handleScan($code);

        $this->dispatch('scan-result',
            found: $found,
            message: $found ? null : ($this->scanError ?? "No product found for \"{$code}\"."),
        );

        $this->scanError = null;
    }

    /** Exact barcode match first, then exact SKU. */
    protected function findScannedProduct(string $code): ?Product
    {
        return Product::where('barcode', $code)->first()
            ?? Product::where('sku', $code)->first();
    }
}

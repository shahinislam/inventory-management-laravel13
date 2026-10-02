<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductList;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Stock\StockAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    openShift();
    Warehouse::factory()->default()->create();
    $this->product = Product::factory()->create(['barcode' => '8901234', 'sku' => 'SKU-1', 'quantity' => 20]);
});

it('adds a scanned product to the POS cart, and one more on a second scan', function () {
    Livewire::test(PosTerminal::class)
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertCount('cart', 1)
        ->assertDispatched('scan-result', found: true)
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertCount('cart', 1)
        ->assertSet('cart.0.quantity', 2);
});

it('finds a product by SKU when the barcode does not match', function () {
    Livewire::test(PosTerminal::class)
        ->dispatch('barcode-scanned', code: 'SKU-1')
        ->assertSet('cart.0.product_id', $this->product->id);
});

it('reports an unknown code', function () {
    Livewire::test(PosTerminal::class)
        ->dispatch('barcode-scanned', code: '0000000')
        ->assertCount('cart', 0)
        ->assertDispatched('scan-result', found: false);
});

it('ignores scans while the POS payment popup is open', function () {
    Livewire::test(PosTerminal::class)
        ->set('showPaymentModal', true)
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertCount('cart', 0)
        ->assertDispatched('scan-result', found: false);
});

it('adds scanned products to invoice and purchase lines', function () {
    Livewire::test(InvoiceForm::class)
        ->dispatch('barcode-scanned', code: '8901234')
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertCount('items', 1)
        ->assertSet('items.0.quantity', '2');

    Livewire::test(PurchaseForm::class)
        ->dispatch('barcode-scanned', code: '8901234')
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertCount('items', 1)
        ->assertSet('items.0.quantity', '2');
});

it('selects the scanned product on stock adjustment', function () {
    Livewire::test(StockAdjustment::class)
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertSet('product_id', $this->product->id);
});

it('searches the product list and fills the product form barcode', function () {
    Livewire::test(ProductList::class)
        ->dispatch('barcode-scanned', code: '8901234')
        ->assertSet('search', '8901234');

    Livewire::test(ProductForm::class)
        ->dispatch('barcode-scanned', code: '5550001')
        ->assertSet('barcode', '5550001');
});

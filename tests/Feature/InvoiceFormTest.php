<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

/** Build a form component with one product line already added. */
function invoiceWith(Product $product, int $qty = 1)
{
    $c = Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('addProduct', $product->id);

    if ($qty > 1) {
        $c->set('items.0.quantity', (string) $qty);
    }

    return $c;
}

it('separates goods subtotal from line tax', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'tax_rate' => 10]);

    $c = invoiceWith($product, 2)->instance();

    expect($c->subtotal)->toBe(1000.0)
        ->and($c->itemTax)->toBe(100.0)
        ->and($c->taxTotal)->toBe(100.0)
        ->and($c->total)->toBe(1100.0);
});

it('adds the manual tax surcharge to line tax', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'tax_rate' => 10]);

    $c = invoiceWith($product, 2)->set('tax', '50')->instance();

    expect($c->taxTotal)->toBe(150.0)
        ->and($c->total)->toBe(1150.0);
});

it('subtracts the invoice-level discount from the total', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'tax_rate' => 10]);

    $c = invoiceWith($product, 2)->set('discount', '100')->instance();

    // 1000 goods - 100 discount + 100 tax
    expect($c->total)->toBe(1000.0);
});

it('stores the real tax on the invoice, not just the surcharge', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'tax_rate' => 10]);

    invoiceWith($product, 2)->call('saveDraft');

    $invoice = Invoice::sole();

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->tax)->toBe(100.0)
        ->and((float) $invoice->total)->toBe(1100.0);
});

it('does not touch stock for a draft invoice', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    invoiceWith($product, 3)->call('saveDraft');

    expect($product->fresh()->quantity)->toBe(10.0)
        ->and(StockMovement::count())->toBe(0);
});

it('deducts stock when saved as paid', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    invoiceWith($product, 3)->call('saveAsPaid');

    expect($product->fresh()->quantity)->toBe(7.0)
        ->and(StockMovement::where('type', 'sale')->count())->toBe(1);
});

it('records the payment inside the same save', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    invoiceWith($product, 2)->call('saveAsPaid');

    expect(Payment::count())->toBe(1)
        ->and((float) Payment::sole()->amount)->toBe(200.0);
});

it('does not deduct stock twice when a paid invoice is re-saved', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    invoiceWith($product, 3)->call('saveAsPaid');
    expect($product->fresh()->quantity)->toBe(7.0);

    // Re-open the saved invoice and save it again.
    $invoice = Invoice::sole();

    Livewire::test(InvoiceForm::class, ['invoice' => $invoice])
        ->call('saveAsPaid');

    // Still 7 — the second save must not deduct again.
    expect($product->fresh()->quantity)->toBe(7.0)
        ->and(StockMovement::where('type', 'sale')->count())->toBe(1)
        ->and(Payment::count())->toBe(1);
});

it('does not increment promotion usage twice on re-save', function () {
    $product = Product::factory()->create(['selling_price' => 200, 'quantity' => 10]);

    $promotion = Promotion::factory()->percentage(25)->create([
        'product_id' => $product->id,
        'usage_limit' => 10,
    ]);

    invoiceWith($product)->call('saveAsPaid');
    expect($promotion->fresh()->used_count)->toBe(1);

    Livewire::test(InvoiceForm::class, ['invoice' => Invoice::sole()])
        ->call('saveAsPaid');

    expect($promotion->fresh()->used_count)->toBe(1);
});

it('requires at least one line item', function () {
    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('saveDraft')
        ->assertHasErrors('items');

    expect(Invoice::count())->toBe(0);
});

it('requires a customer name', function () {
    $product = Product::factory()->create();

    Livewire::test(InvoiceForm::class)
        ->call('addProduct', $product->id)
        ->call('saveDraft')
        ->assertHasErrors('customer_name');
});

it('merges a duplicate product into the existing line', function () {
    $product = Product::factory()->create();

    Livewire::test(InvoiceForm::class)
        ->call('addProduct', $product->id)
        ->call('addProduct', $product->id)
        ->assertCount('items', 1)
        ->assertSet('items.0.quantity', '2');
});

<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Pos\PosTerminal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();
});

it('adds the courier charge to the POS cart total', function () {
    $product = Product::factory()->create([
        'selling_price' => 100,
        'discount' => 0,
        'tax_rate' => 0,
        'quantity' => 10,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->assertSet('cart.0.price', 100.0)
        ->set('hasCourier', true)
        ->set('courierCharge', '60')
        ->tap(fn ($c) => expect($c->instance()->cartTotal)->toBe(160.0));
});

it('leaves the cart total alone while the courier tick is off', function () {
    $product = Product::factory()->create([
        'selling_price' => 100,
        'discount' => 0,
        'tax_rate' => 0,
        'quantity' => 10,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->set('courierCharge', '60')
        ->set('hasCourier', false)
        ->tap(fn ($c) => expect($c->instance()->cartTotal)->toBe(100.0));
});

it('never overwrites a courier cost on the server when the charge changes', function () {
    // Mirroring the charge into the cost is done in the browser. The server
    // must not do it, or a batch of deferred updates could clobber a cost the
    // cashier typed by hand.
    Livewire::test(PosTerminal::class)
        ->set('hasCourier', true)
        ->set('courierCost', '120')
        ->set('courierCharge', '60')
        ->assertSet('courierCost', '120');
});

it('persists both courier amounts when a POS sale completes', function () {
    $product = Product::factory()->create([
        'selling_price' => 100,
        'discount' => 0,
        'tax_rate' => 0,
        'quantity' => 10,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->set('hasCourier', true)
        ->set('courierCharge', '60')
        ->set('courierCost', '80')
        ->call('openPaymentModal')
        ->set('amountReceived', '160')
        ->call('completeSale');

    $invoice = Invoice::latest('id')->first();

    expect((float) $invoice->courier_charge)->toBe(60.0)
        ->and((float) $invoice->courier_cost)->toBe(80.0)
        ->and((float) $invoice->total)->toBe(160.0)
        // The shop ate 20 of the delivery cost.
        ->and($invoice->courier_margin)->toBe(-20.0);
});

it('clears courier state after a sale', function () {
    $product = Product::factory()->create([
        'selling_price' => 100,
        'discount' => 0,
        'tax_rate' => 0,
        'quantity' => 10,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->set('hasCourier', true)
        ->set('courierCharge', '60')
        ->call('openPaymentModal')
        ->set('amountReceived', '160')
        ->call('completeSale')
        ->assertSet('hasCourier', false)
        ->assertSet('courierCharge', '0')
        ->assertSet('courierCost', '0');
});

it('adds the courier charge to the invoice form total and saves it', function () {
    $product = Product::factory()->create([
        'selling_price' => 250,
        'discount' => 0,
        'tax_rate' => 0,
        'quantity' => 10,
    ]);

    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Buyer')
        ->call('addProduct', $product->id)
        ->set('hasCourier', true)
        ->set('courierCharge', '40')
        ->set('courierCost', '40')
        ->tap(fn ($c) => expect($c->instance()->total)->toBe(290.0))
        ->call('saveAsPaid');

    $invoice = Invoice::latest('id')->first();

    expect((float) $invoice->courier_charge)->toBe(40.0)
        ->and((float) $invoice->total)->toBe(290.0)
        // Pass-through: the charge covered the cost exactly.
        ->and($invoice->courier_margin)->toBe(0.0);
});

it('rehydrates courier amounts when editing an invoice without overwriting the cost', function () {
    $invoice = Invoice::create([
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'customer_name' => 'Test Buyer',
        'status' => 'paid',
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'courier_charge' => 0,
        'courier_cost' => 90,
        'total' => 100,
        'paid_amount' => 100,
        'due_amount' => 0,
        'invoice_date' => now(),
    ]);

    Livewire::test(InvoiceForm::class, ['invoice' => $invoice])
        ->assertSet('hasCourier', true)
        ->assertSet('courierCharge', '0.00')
        ->assertSet('courierCost', '90.00')
        // A stored cost is the user's own figure — typing a charge must not clobber it.
        ->set('courierCharge', '30')
        ->assertSet('courierCost', '90.00');
});

it('keeps the courier cost off the customer-facing receipt and pdf', function () {
    $invoice = Invoice::create([
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'customer_name' => 'Test Buyer',
        'status' => 'paid',
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'courier_charge' => 50,
        'courier_cost' => 999.77, // distinctive, so a leak is unmistakable
        'total' => 150,
        'paid_amount' => 150,
        'due_amount' => 0,
        'invoice_date' => now(),
    ]);

    $company = ['name' => 'Test Co'];

    $receipt = view('pdf.receipt', compact('invoice', 'company'))->render();
    $pdf = view('pdf.invoice', compact('invoice', 'company'))->render();

    expect($receipt)->not->toContain('999.77')
        ->and($pdf)->not->toContain('999.77');
});

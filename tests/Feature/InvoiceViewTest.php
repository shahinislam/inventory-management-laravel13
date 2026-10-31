<?php

use App\Livewire\Invoices\InvoiceView;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesReturnService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

/** A 10 × 100 sale with $paid of it paid in cash. */
function ivSale(float $paid, array $attrs = []): Invoice
{
    $product = Product::factory()->create(['name' => 'Kettle']);
    $sale = Invoice::create($attrs + [
        'warehouse_id' => Warehouse::first()->id, 'created_by' => auth()->id(), 'customer_name' => 'Buyer',
        'status' => $paid >= 1000 ? 'paid' : ($paid > 0 ? 'partial' : 'sent'),
        'payment_method' => 'cash',
        'subtotal' => 1000, 'total' => 1000, 'paid_amount' => $paid, 'due_amount' => 1000 - $paid,
        'invoice_date' => today(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $sale->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'product_sku' => $product->sku,
        'quantity' => 10, 'unit_price' => 100, 'subtotal' => 1000,
    ]);

    if ($paid > 0) {
        Payment::create([
            'invoice_id' => $sale->id, 'created_by' => auth()->id(),
            'amount' => $paid, 'method' => 'cash', 'status' => 'completed', 'payment_date' => today(),
        ]);
    }

    return $sale->fresh('items');
}

// ---------- after a return ----------

it('shows returned, the net total, refunds and the balance after a refunded return', function () {
    openShift();
    $sale = ivSale(1000);
    app(SalesReturnService::class)->process($sale, [$sale->items->sole()->id => ['quantity' => 3]], 'cash');

    $sale->refresh();
    expect((float) $sale->paid_amount)->toBe(700.0)
        ->and((float) $sale->returned_amount)->toBe(300.0);

    // Total stays the original bill; returns come after it, then what is left.
    $this->get(route('invoices.show', $sale))
        ->assertOk()
        ->assertSeeInOrder([
            'Total', money(1000),
            'Returned', '−'.money(300),
            'Net total', money(700),
            'Paid', money(1000),
            'Refunded', '−'.money(300),
            'Balance Due', money(0),
        ]);

    $this->get(route('invoices.receipt', $sale))
        ->assertOk()
        ->assertSeeInOrder([
            'TOTAL', money(1000),
            'Returned', '−'.money(300, false),
            $sale->returns()->sole()->invoice_number,
            'NET TOTAL', money(700),
            'Cash', money(1000, false),
            'Refunded', '−'.money(300, false),
            'Balance Due', money(0, false),
        ]);
});

it('shows the balance still owed after a return taken off the due', function () {
    $sale = ivSale(400);
    app(SalesReturnService::class)->process($sale, [$sale->items->sole()->id => ['quantity' => 3]], 'cash');

    $sale->refresh();
    expect((float) $sale->due_amount)->toBe(300.0);

    // 1000 − 300 returned = 700, of which 400 is paid: 300 to go. Nothing was refunded.
    $this->get(route('invoices.show', $sale))
        ->assertOk()
        ->assertSeeInOrder(['Returned', '−'.money(300), 'Net total', money(700), 'Paid', money(400), 'Balance Due', money(300)])
        ->assertDontSee('Refunded');

    $this->get(route('invoices.receipt', $sale))
        ->assertOk()
        ->assertSeeInOrder(['NET TOTAL', money(700), 'Balance Due', money(300, false)])
        ->assertDontSee('Refunded');
});

it('leaves the totals alone when nothing came back', function () {
    $sale = ivSale(1000);

    $this->get(route('invoices.show', $sale))->assertOk()->assertDontSee('Net total')->assertDontSee('Refunded');
    $this->get(route('invoices.receipt', $sale))->assertOk()->assertDontSee('NET TOTAL')->assertDontSee('Returned');
});

// ---------- tax-on-top labels ----------

it('labels line totals and the subtotal when tax is added on top', function () {
    // 10 × 100 net + 10% on top: lines show 1100 with tax, the subtotal 1000 without.
    $sale = ivSale(1100, ['tax' => 100, 'total' => 1100, 'due_amount' => 0]);
    $sale->items->sole()->update(['tax_rate' => 10, 'tax_amount' => 100, 'subtotal' => 1100]);

    $this->get(route('invoices.show', $sale))
        ->assertOk()
        ->assertSeeInOrder(['Total incl. tax', money(1100), 'Subtotal excl. tax', money(1000), 'Tax', money(100)]);

    $this->get(route('invoices.receipt', $sale))
        ->assertOk()
        ->assertSeeInOrder(['Incl. tax', money(1100, false), 'Subtotal excl. tax', money(1000, false)]);
});

it('keeps the plain labels when prices include the tax', function () {
    $sale = ivSale(1000, ['prices_include_tax' => true, 'subtotal' => 869.57, 'tax' => 130.43]);
    $sale->items->sole()->update(['tax_rate' => 15, 'tax_amount' => 130.43]);

    $this->get(route('invoices.show', $sale))
        ->assertOk()
        ->assertDontSee('Total incl. tax')
        ->assertDontSee('Subtotal excl. tax')
        ->assertSee('Includes tax '.money(130.43));
});

// ---------- cash needs an open shift ----------

it('refuses a cash payment when no shift is open', function () {
    $sale = ivSale(0);

    Livewire::test(InvoiceView::class, ['invoice' => $sale])
        ->call('openPaymentModal')
        ->assertSee('You have no open shift')
        ->set('payment_amount', '400')
        ->set('payment_method', 'cash')
        ->call('recordPayment')
        ->assertHasErrors('payment_method');

    expect(Payment::count())->toBe(0)
        ->and((float) $sale->fresh()->due_amount)->toBe(1000.0);
});

it('takes a cash payment into the open shift', function () {
    $shift = openShift();
    $sale = ivSale(0);

    Livewire::test(InvoiceView::class, ['invoice' => $sale])
        ->call('openPaymentModal')
        ->assertDontSee('You have no open shift')
        ->set('payment_amount', '400')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect(Payment::sole()->shift_id)->toBe($shift->id)
        ->and((float) $sale->fresh()->due_amount)->toBe(600.0);
});

it('takes a non-cash payment without a shift', function () {
    $sale = ivSale(0);

    Livewire::test(InvoiceView::class, ['invoice' => $sale])
        ->call('openPaymentModal')
        ->set('payment_amount', '400')
        ->set('payment_method', 'cheque')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect(Payment::sole()->shift_id)->toBeNull()
        ->and((float) $sale->fresh()->due_amount)->toBe(600.0);
});

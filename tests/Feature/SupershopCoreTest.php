<?php

use App\Livewire\Pos\PosTerminal;
use App\Livewire\Returns\ReturnForm;
use App\Livewire\Shifts\ShiftPanel;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SalesReturnService;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    $this->inventory = app(InventoryService::class);
});

/** Ring up a sale at the POS and return the invoice. */
function posSale(array $lines, ?Customer $customer = null, array $set = []): Invoice
{
    $pos = Livewire::test(PosTerminal::class);
    if ($customer) {
        $pos->call('selectCustomer', $customer->id);
    }
    foreach ($lines as [$product, $qty]) {
        $pos->call('addToCart', $product->id, (float) $qty);
    }
    foreach ($set as $key => $value) {
        $pos->set($key, $value);
    }
    $pos->call('openPaymentModal')->call('completeSale')->assertHasNoErrors();

    return Invoice::sales()->latest('id')->first();
}

// ============ SHIFTS ============

it('blocks selling until a shift is open', function () {
    $product = Product::factory()->create(['quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->assertSee('Open your shift')
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Invoice::count())->toBe(0);
});

it('opens a shift from the POS and stamps sales and payments with it', function () {
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);

    Livewire::test(PosTerminal::class)->set('openingCash', '500')->call('openShift')->assertHasNoErrors();
    $shift = CashShift::sole();

    $invoice = posSale([[$product, 2]]);

    expect($invoice->shift_id)->toBe($shift->id)
        ->and($invoice->payments()->first()->shift_id)->toBe($shift->id);
});

it('works out expected cash and the difference at close', function () {
    $shift = openShift(1000);
    $product = Product::factory()->create(['quantity' => 50, 'selling_price' => 200]);
    posSale([[$product, 3]]);                                   // +600 cash
    app(ShiftService::class)->moveCash($shift, 'out', 150, 'Bank drop');
    app(ShiftService::class)->moveCash($shift, 'in', 50, 'Change');

    expect(app(ShiftService::class)->summary($shift)['expected_cash'])->toBe(1500.0);

    Livewire::test(ShiftPanel::class)->set('countedCash', '1480')->call('closeShift')->assertHasNoErrors();

    $shift->refresh();
    expect($shift->status)->toBe('closed')
        ->and((float) $shift->expected_cash)->toBe(1500.0)
        ->and((float) $shift->difference)->toBe(-20.0);
});

// ============ LOOSE GOODS ============

it('sells a loose item by weight and keeps the fraction in stock', function () {
    openShift();
    $rice = Product::factory()->create(['unit' => 'kg', 'quantity' => 10, 'selling_price' => 80]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $rice->id)
        ->assertSet('showWeighModal', true)
        ->set('weighQuantity', '0.750')
        ->call('confirmWeight')
        ->assertSet('cart.0.quantity', 0.75)
        ->call('openPaymentModal')
        ->call('completeSale');

    $invoice = Invoice::sales()->sole();
    expect((float) $invoice->total)->toBe(60.0)
        ->and($rice->fresh()->quantity)->toBe(9.25)
        ->and($this->inventory->stockIn($rice->id, $this->warehouse->id))->toBe(9.25);
});

it('refuses a weight above what is in stock', function () {
    openShift();
    $rice = Product::factory()->create(['unit' => 'kg', 'quantity' => 2]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $rice->id)
        ->set('weighQuantity', '2.5')
        ->call('confirmWeight')
        ->assertHasErrors('weighQuantity')
        ->assertCount('cart', 0);
});

it('keeps counted goods whole', function () {
    openShift();
    $soap = Product::factory()->create(['unit' => 'pcs', 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $soap->id)
        ->call('updateQty', 0, '2.6')
        ->assertSet('cart.0.quantity', 2.0);
});

// ============ BATCHES / FEFO ============

it('sells the earliest expiry batch first and moves batches on transfer', function () {
    $milk = Product::factory()->create(['quantity' => 0, 'cost_price' => 50]);
    $this->inventory->add($milk->id, $this->warehouse->id, 5, 'purchase', ['batch_number' => 'LATE', 'expiry_date' => now()->addDays(30)->toDateString(), 'unit_cost' => 60]);
    $this->inventory->add($milk->id, $this->warehouse->id, 3, 'purchase', ['batch_number' => 'SOON', 'expiry_date' => now()->addDays(3)->toDateString(), 'unit_cost' => 40]);

    $taken = $this->inventory->remove($milk->id, $this->warehouse->id, 4, 'sale');

    expect(StockBatch::where('batch_number', 'SOON')->exists())->toBeFalse()
        ->and(StockBatch::where('batch_number', 'LATE')->value('quantity'))->toBe(4.0)
        ->and($taken['unit_cost'])->toBe(45.0);                     // (3×40 + 1×60) / 4

    $other = Warehouse::factory()->create();
    $this->inventory->transfer($milk->id, $this->warehouse->id, $other->id, 2);

    expect(StockBatch::where('warehouse_id', $other->id)->where('batch_number', 'LATE')->value('quantity'))->toBe(2.0);
});

it('moves the product cost to the weighted average on purchase', function () {
    $item = Product::factory()->create(['quantity' => 10, 'cost_price' => 100]);
    $this->inventory->add($item->id, $this->warehouse->id, 10, 'purchase', ['unit_cost' => 120]);

    expect((float) $item->fresh()->cost_price)->toBe(110.0);
});

// ============ RETURNS ============

it('returns part of a paid sale for cash and puts it back in stock', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);
    $sale = posSale([[$product, 4]]);

    Livewire::test(ReturnForm::class)
        ->call('selectInvoice', $sale->id)
        ->set("lines.{$sale->items->first()->id}.quantity", '1')
        ->set('refundMethod', 'cash')
        ->call('submit')
        ->assertHasNoErrors();

    $return = Invoice::query()->returns()->sole();
    expect($return->parent_invoice_id)->toBe($sale->id)
        ->and((float) $return->total)->toBe(100.0)
        ->and($return->payments()->first()->status)->toBe('refunded')
        ->and($product->fresh()->quantity)->toBe(7.0)
        ->and((float) $sale->fresh()->returned_amount)->toBe(100.0)
        ->and(Invoice::sales()->count())->toBe(1);
});

it('takes a return off the customer due before paying anything back', function () {
    openShift();
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);
    $sale = posSale([[$product, 3]], $customer, ['payLater' => true, 'paidNow' => '100']); // due 200

    app(SalesReturnService::class)->process($sale, [$sale->items->first()->id => ['quantity' => 1]], 'cash');

    $sale->refresh();
    expect((float) $sale->due_amount)->toBe(100.0)
        ->and(Payment::where('status', 'refunded')->count())->toBe(0);
});

it('does not restock damaged returns', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);
    $sale = posSale([[$product, 2]]);

    app(SalesReturnService::class)->process($sale, [$sale->items->first()->id => ['quantity' => 1, 'restock' => false]], 'cash');

    expect($product->fresh()->quantity)->toBe(8.0);
});

it('refuses to return more than was sold', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);
    $sale = posSale([[$product, 2]]);
    $itemId = $sale->items->first()->id;

    app(SalesReturnService::class)->process($sale, [$itemId => ['quantity' => 2]], 'cash');

    expect(fn () => app(SalesReturnService::class)->process($sale->fresh(), [$itemId => ['quantity' => 1]], 'cash'))
        ->toThrow(ValidationException::class);
    expect($sale->fresh()->status)->toBe('returned');
});

it('shares the bill discount across returned lines', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);
    $sale = posSale([[$product, 4]], null, ['discount' => '40']);   // paid 360

    $return = app(SalesReturnService::class)->process($sale, [$sale->items->first()->id => ['quantity' => 2]], 'cash');

    expect((float) $return->total)->toBe(180.0);
});

// ============ HOLD / RESUME ============

it('holds a sale without moving stock and resumes it', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 50]);

    $pos = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id, 3.0)
        ->call('openHoldModal')
        ->set('holdLabel', 'Blue shirt')
        ->call('holdSale')
        ->assertCount('cart', 0);

    $held = Invoice::query()->held()->sole();
    expect($held->held_label)->toBe('Blue shirt')
        ->and($product->fresh()->quantity)->toBe(10.0)
        ->and(Invoice::sales()->count())->toBe(0)
        ->and(str_starts_with($held->invoice_number, 'HOLD-'))->toBeTrue();

    $pos->call('resumeHeld', $held->id)
        ->assertCount('cart', 1)
        ->assertSet('cart.0.quantity', 3.0);

    expect(Invoice::query()->held()->count())->toBe(0);
});

// ============ SPLIT / MOBILE BANKING ============

it('splits one bill across cash and bKash', function () {
    openShift();
    $wallet = PaymentAccount::create(['name' => 'bKash Merchant', 'type' => 'mobile_wallet', 'is_active' => true]);
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 500]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('enableSplit')
        ->set('splits.0.amount', '200')
        ->set('splits.1.amount', '300')
        ->set('splits.1.account_id', $wallet->id)
        ->set('splits.1.reference', 'TRX123')
        ->call('completeSale')
        ->assertHasNoErrors();

    $invoice = Invoice::sales()->sole();
    expect($invoice->payment_method)->toBe('split')
        ->and($invoice->payments()->pluck('amount', 'method')->map(fn ($a) => (float) $a)->all())
        ->toBe(['cash' => 200.0, 'mobile_banking' => 300.0]);
});

it('refuses a split that does not add up', function () {
    openShift();
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 500]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('enableSplit')
        ->set('splits.0.amount', '100')
        ->set('splits.1.method', 'card')
        ->set('splits.1.amount', '100')
        ->call('completeSale')
        ->assertHasErrors('splits');

    expect(Invoice::count())->toBe(0);
});

it('needs the transaction ID for a bKash payment', function () {
    openShift();
    $wallet = PaymentAccount::create(['name' => 'Nagad', 'type' => 'mobile_wallet', 'is_active' => true]);
    $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 100]);

    $pos = Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('setPaymentMethod', 'mobile_banking')
        ->assertSet('paymentAccountId', $wallet->id)
        ->call('completeSale')
        ->assertHasErrors('paymentReference');

    $pos->set('paymentReference', 'NG77')->call('completeSale')->assertHasNoErrors();

    expect(Payment::sole()->reference)->toBe('NG77');
});

// ============ SCREENS ============

it('renders the new pages', function () {
    openShift();

    foreach (['shifts.current', 'shifts.index', 'returns.create', 'expenses.index', 'reports.profit-loss',
        'reports.expiry', 'stock.count', 'products.labels', 'marketing.sms', 'marketing.sms-log'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('invoices.index', ['kind' => 'returns']))->assertOk();
    $this->get(route('invoices.index', ['kind' => 'held']))->assertOk();
    $this->get(route('shifts.report', CashShift::sole()))->assertOk()->assertSee('SHIFT REPORT');
});

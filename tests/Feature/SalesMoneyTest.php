<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Returns\ReturnForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MembershipReward;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PricingService;
use App\Services\SalesReturnService;
use App\Services\ShiftService;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 * Money that has to agree across features: discounts, tax, returns, dues and
 * the customer's figures, including what the browser can send back.
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();
});

function plainProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + ['selling_price' => 100, 'tax_rate' => 0, 'discount' => 0, 'quantity' => 50]);
}

/** A finished POS sale: lines are [product, qty]; $set fills POS fields before payment. */
function sale(array $lines, ?Customer $customer = null, array $set = []): Invoice
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

    return Invoice::with('items')->sales()->latest('id')->first();
}

function giveBack(Invoice $sale, array $quantities, string $method = 'cash'): Invoice
{
    $lines = [];
    foreach ($quantities as $index => $qty) {
        $lines[$sale->items[$index]->id] = ['quantity' => $qty];
    }

    return app(SalesReturnService::class)->process($sale->fresh('items'), $lines, $method);
}

// ============ DISCOUNTS ============

it('never discounts a unit below zero', function () {
    $product = plainProduct(['selling_price' => 0.99, 'discount_type' => 'fixed', 'discount' => 2]);

    expect(app(PricingService::class)->resolveDiscount($product)->perUnit)->toBe(0.99);
});

it('rounds a percentage discount to whole cents', function () {
    // 5% of 2.49 is 0.1245: the line stores 0.12, so it must be priced at 0.12.
    $product = plainProduct(['selling_price' => 2.49, 'discount_type' => 'percentage', 'discount' => 5]);

    expect(app(PricingService::class)->resolveDiscount($product)->perUnit)->toBe(0.12);

    $invoice = sale([[$product, 4]]);

    expect((float) $invoice->total)->toBe(9.48)
        ->and((float) $invoice->items->sole()->discount)->toBe(0.12);
});

// ============ WHAT THE BROWSER SENDS ============

it('refuses negative bill figures and a discount larger than the goods', function (string $field, string $value) {
    Livewire::test(PosTerminal::class)
        ->call('addToCart', plainProduct()->id)
        ->set($field, $value)
        ->call('openPaymentModal')
        ->assertHasErrors($field)
        ->assertSet('showPaymentModal', false);
})->with([
    'negative tax' => ['tax', '-10'],
    'negative discount' => ['discount', '-5'],
    'negative courier' => ['courierCharge', '-3'],
    'discount over the goods' => ['discount', '150'],
]);

it('hands out a reward gift once however often the line is copied', function () {
    $customer = Customer::factory()->create();
    $gift = plainProduct(['selling_price' => 50, 'quantity' => 10]);
    $rule = MembershipReward::create([
        'name' => 'Free gift', 'basis' => 'single_invoice', 'min_amount' => 50, 'max_amount' => null,
        'reward_type' => 'gift', 'value' => 0, 'gift_product_id' => $gift->id, 'gift_quantity' => 1, 'is_active' => true,
    ]);

    $pos = Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', plainProduct()->id)
        ->call('applyReward', $rule->id);

    $giftLine = $pos->get('cart')[1];
    $pos->set('cart', [...$pos->get('cart'), $giftLine, $giftLine])
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(Invoice::sole()->items()->where('is_gift', true)->count())->toBe(1)
        ->and($gift->stockIn($this->warehouse->id))->toBe(9.0);
});

it('keeps the member reward of an invoice out of the browser\'s reach', function () {
    Livewire::test(InvoiceForm::class)->set('membershipDiscount', 50);
})->throws(CannotUpdateLockedPropertyException::class);

// ============ CUSTOMER FIGURES AND CREDIT ============

it('shows the same balance on the customer as on the Due report', function () {
    $customer = Customer::factory()->create();
    sale([[plainProduct(), 2]], $customer, ['payLater' => true, 'paidNow' => '50']); // 200, 150 owed
    sale([[plainProduct(), 1]], $customer);                                         // 100 paid

    $row = Customer::withMoney()->find($customer->id);

    expect($row->orders_count)->toBe(2)
        ->and((float) $row->spent)->toBe(300.0)
        ->and((float) $row->balance_due)->toBe(150.0)
        ->and($customer->owed())->toBe(150.0)
        ->and((float) Invoice::sales()->withDue()->sum('due_amount'))->toBe(150.0);

    $this->get(route('customers.index'))->assertOk()->assertSee(money(150));
});

it('stops a sale on due that would take the customer over the credit limit', function () {
    $customer = Customer::factory()->create(['credit_limit' => 150]);
    $product = plainProduct();

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id, 2.0)
        ->call('openPaymentModal')
        ->set('payLater', true)
        ->set('paidNow', '0')
        ->call('completeSale')
        ->assertHasErrors('paidNow');

    expect(Invoice::count())->toBe(0);

    // 60 now leaves 140 owed: inside the limit.
    sale([[$product, 2]], $customer, ['payLater' => true, 'paidNow' => '60']);

    expect($customer->owed())->toBe(140.0)
        ->and($customer->creditLeft())->toBe(10.0);
});

// ============ RETURNS ============

it('refunds a line returned piece by piece to exactly what was paid', function () {
    // 3 for 10.00 after a 10% bill discount on 11.11.
    $product = plainProduct(['selling_price' => 3.70]);
    $sale = sale([[$product, 3]], null, ['discount' => '1.10']);

    expect((float) $sale->total)->toBe(10.0);

    $refunds = collect([1, 1, 1])->map(fn ($qty) => (float) giveBack($sale, [$qty])->total);

    expect($refunds->all())->toBe([3.33, 3.33, 3.34])
        ->and($sale->fresh()->status)->toBe('returned')
        ->and((float) $sale->fresh()->paid_amount)->toBe(0.0);
});

it('refunds the share of tax that was added by hand', function () {
    $product = plainProduct();
    $sale = sale([[$product, 2]], null, ['tax' => '10']); // 200 + 10

    $return = giveBack($sale, [2]);

    expect((float) $return->total)->toBe(210.0)
        ->and((float) $return->tax)->toBe(10.0);
});

it('keeps a fully returned sale on the dues list while something is still owed', function () {
    $customer = Customer::factory()->create();
    // 100 of goods + 10 courier, nothing paid.
    $sale = sale([[plainProduct(), 1]], $customer, [
        'hasCourier' => true, 'courierCharge' => '10', 'payLater' => true, 'paidNow' => '0',
    ]);

    giveBack($sale, [1]);

    $sale->refresh();
    expect((float) $sale->due_amount)->toBe(10.0)
        ->and($sale->status)->not->toBe('returned')
        ->and(Invoice::sales()->withDue()->pluck('id')->all())->toBe([$sale->id]);
});

it('only refunds to an active account of the right kind', function () {
    $sale = sale([[plainProduct(), 1]]);
    $bank = PaymentAccount::create(['name' => 'Main Checking', 'type' => 'bank', 'is_active' => true]);

    expect(fn () => app(SalesReturnService::class)->process(
        $sale->fresh('items'), [$sale->items[0]->id => ['quantity' => 1]], 'card', $bank->id,
    ))->toThrow(ValidationException::class);
});

it('previews the same refund the return will give', function () {
    $product = plainProduct(['selling_price' => 3.70]);
    $sale = sale([[$product, 3]], null, ['discount' => '1.10']);
    giveBack($sale, [2]);

    $form = Livewire::test(ReturnForm::class)
        ->call('selectInvoice', $sale->id)
        ->set('lines.'.$sale->items[0]->id.'.quantity', '1');

    // 6.67 went back for the first two; the last one takes the rest of the 10.00.
    expect($form->instance()->returnValue)->toBe(3.33);
});

it('gives the last unit the remainder after an uneven split', function () {
    $product = plainProduct(['selling_price' => 3.70]);
    $sale = sale([[$product, 3]], null, ['discount' => '1.10']);

    $first = giveBack($sale, [2]);
    $last = giveBack($sale, [1]);

    expect((float) $first->total + (float) $last->total)->toBe(10.0);
});

// ============ CASH ============

it('takes cash on the invoice form only inside an open shift', function () {
    app(ShiftService::class)->currentFor($this->user)->update(['status' => 'closed']);

    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('addProduct', plainProduct()->id)
        ->set('payment_method', 'cash')
        ->call('saveAsPaid')
        ->assertHasErrors('payment_method');

    expect(Invoice::count())->toBe(0);
});

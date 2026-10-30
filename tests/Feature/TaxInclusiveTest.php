<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Reports\ProfitLossReport;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MembershipReward;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PricingService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();
});

function pricesIncludeTax(bool $on = true): void
{
    Setting::set('tax.prices_include_tax', $on ? 'true' : 'false');
}

/** A shelf price of 115 with 15% tax inside it: 100 net + 15 tax. */
function taxedProduct(array $attributes = []): Product
{
    return Product::factory()->create($attributes + ['selling_price' => 115, 'tax_rate' => 15, 'quantity' => 50]);
}

function sellAtPos(Product $product, int $qty = 2): void
{
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('updateQty', 0, $qty)
        ->call('openPaymentModal')
        ->call('completeSale');
}

// ============ LINE MATHS ============

it('splits a line with tax added on top', function () {
    expect(PricingService::lineAmounts(100, 0, 2, 15, false))
        ->toBe(['net' => 200.0, 'tax' => 30.0, 'gross' => 230.0]);
});

it('extracts the tax from a price that already contains it', function () {
    expect(PricingService::lineAmounts(115, 0, 2, 15, true))
        ->toBe(['net' => 200.0, 'tax' => 30.0, 'gross' => 230.0]);
});

it('takes the line discount off before extracting tax and keeps net + tax = gross', function () {
    // (10.99 - 1) x 3 = 29.97 charged; 7.5% inside it.
    $line = PricingService::lineAmounts(10.99, 1, 3, 7.5, true);

    expect($line['gross'])->toBe(29.97)
        ->and($line['tax'])->toBe(2.09)
        ->and(round($line['net'] + $line['tax'], 2))->toBe($line['gross']);
});

// ============ POS ============

it('charges the shelf price at the POS when prices include tax', function () {
    pricesIncludeTax();

    $component = Livewire::test(PosTerminal::class)
        ->call('addToCart', taxedProduct()->id)
        ->call('updateQty', 0, 2);

    expect($component->instance()->cartSubtotal)->toBe(200.0)
        ->and($component->instance()->cartItemTax)->toBe(30.0)
        ->and($component->instance()->cartGoodsTotal)->toBe(230.0)
        ->and($component->instance()->cartTotal)->toBe(230.0);
});

it('stores an inclusive POS sale with the same meanings as an exclusive one', function () {
    pricesIncludeTax();

    sellAtPos(taxedProduct());

    $invoice = Invoice::with('items')->sole();
    $item = $invoice->items->sole();

    expect($invoice->prices_include_tax)->toBeTrue()
        ->and((float) $invoice->subtotal)->toBe(200.0)
        ->and((float) $invoice->tax)->toBe(30.0)
        ->and((float) $invoice->total)->toBe(230.0)
        ->and((float) $item->unit_price)->toBe(115.0)
        ->and((float) $item->subtotal)->toBe(230.0)
        ->and((float) $item->tax_amount)->toBe(30.0)
        ->and($item->netAmount())->toBe(200.0);
});

it('records the line tax on exclusive sales too', function () {
    sellAtPos(taxedProduct(['selling_price' => 100]));

    $invoice = Invoice::with('items')->sole();

    expect($invoice->prices_include_tax)->toBeFalse()
        ->and((float) $invoice->total)->toBe(230.0)
        ->and((float) $invoice->items->sole()->tax_amount)->toBe(30.0);
});

it('judges member rewards on the goods value the customer sees', function () {
    pricesIncludeTax();
    $customer = Customer::factory()->create();

    // 230 on the bill, 200 without tax: only the shelf total reaches 220.
    MembershipReward::create([
        'name' => 'Big basket', 'basis' => 'single_invoice', 'min_amount' => 220, 'max_amount' => null,
        'reward_type' => 'fixed', 'value' => 10, 'gift_quantity' => 1, 'is_active' => true,
    ]);

    $component = Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', taxedProduct()->id)
        ->call('updateQty', 0, 2);

    expect($component->instance()->eligibleRewards)->toHaveCount(1);
});

// ============ DOCUMENTS ============

it('prints the tax as included on the receipt and the invoice page', function () {
    pricesIncludeTax();
    sellAtPos(taxedProduct());
    $invoice = Invoice::sole();

    $this->get(route('invoices.receipt', $invoice))
        ->assertOk()
        ->assertSee('incl. 15%')
        ->assertSee('Includes tax')
        ->assertSee(money(30, false));

    $this->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee('15% incl.')
        ->assertSee('Includes tax '.money(30));
});

it('prints the shop tax number on the receipt and the invoice page only when set', function () {
    sellAtPos(taxedProduct());
    $invoice = Invoice::sole();

    $this->get(route('invoices.receipt', $invoice))->assertOk()->assertDontSee('Tax No:');

    Setting::set('company.tax_number', 'TX-48210-7');

    $this->get(route('invoices.receipt', $invoice))->assertOk()->assertSee('Tax No: TX-48210-7');
    $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('Tax No: TX-48210-7');
});

it('reports category sales without the tax inside inclusive prices', function () {
    pricesIncludeTax();
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    sellAtPos(taxedProduct(['category_id' => $drinks->id, 'cost_price' => 60]));

    $rows = collect((new ProfitLossReport)->categoryBreakdown(now()->startOfMonth(), now()->endOfMonth()));

    expect($rows->firstWhere('category', 'Drinks')['sales'])->toEqual(200.0);
});

// ============ INVOICE FORM ============

it('prices invoice form lines with the tax included', function () {
    pricesIncludeTax();

    $c = Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('addProduct', taxedProduct()->id)
        ->set('items.0.quantity', '2')
        ->instance();

    expect($c->subtotal)->toBe(200.0)
        ->and($c->itemTax)->toBe(30.0)
        ->and($c->taxTotal)->toBe(30.0)
        ->and($c->goodsTotal)->toBe(230.0)
        ->and($c->total)->toBe(230.0);
});

it('keeps a draft in the mode it was priced in when the setting changes', function () {
    pricesIncludeTax();

    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('addProduct', taxedProduct()->id)
        ->set('items.0.quantity', '2')
        ->call('saveDraft');

    $invoice = Invoice::sole();
    expect($invoice->prices_include_tax)->toBeTrue()
        ->and((float) $invoice->total)->toBe(230.0);

    pricesIncludeTax(false);

    $edit = Livewire::test(InvoiceForm::class, ['invoice' => $invoice]);

    expect($edit->instance()->pricesIncludeTax)->toBeTrue()
        ->and($edit->instance()->total)->toBe(230.0);
});

it('does not count line tax twice when a draft is edited and saved again', function () {
    $product = Product::factory()->create(['selling_price' => 500, 'tax_rate' => 10]);

    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test Customer')
        ->call('addProduct', $product->id)
        ->set('items.0.quantity', '2')
        ->set('tax', '50')
        ->call('saveDraft');

    $invoice = Invoice::sole();
    expect((float) $invoice->tax)->toBe(150.0);

    // Only the hand-added 50 goes back into the field; the 100 line tax is recalculated.
    Livewire::test(InvoiceForm::class, ['invoice' => $invoice])
        ->assertSet('tax', '50')
        ->call('saveDraft');

    expect((float) $invoice->fresh()->tax)->toBe(150.0)
        ->and((float) $invoice->fresh()->total)->toBe(1150.0);
});

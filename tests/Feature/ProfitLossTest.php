<?php

use App\Livewire\Reports\ProfitLossReport;
use App\Livewire\Stock\StockAdjustment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SalesReturnService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

function plInvoice(array $attrs): Invoice
{
    return Invoice::create($attrs + [
        'created_by' => auth()->id(),
        'customer_name' => 'Walk-in',
        'status' => 'paid',
        'invoice_date' => now(),
    ]);
}

function plItem(Invoice $invoice, Product $product, float $qty, float $price, float $cost, array $extra = []): InvoiceItem
{
    return InvoiceItem::create($extra + [
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => $qty,
        'unit_price' => $price,
        'unit_cost' => $cost,
        'subtotal' => round($qty * $price, 2),
    ]);
}

function plExpense(string $category, float $amount, string $type = 'expense', $date = null): Expense
{
    return Expense::create([
        'type' => $type,
        'category' => $category,
        'amount' => $amount,
        'expense_date' => $date ?? today(),
        'method' => 'cash',
        'created_by' => auth()->id(),
    ]);
}

/**
 * This month:
 *   Sale 1  Drinks 10 × 100 (cost 60), VAT 50, bill discount 100, courier 60 billed / 80 paid
 *   Sale 2  Uncategorised 5 × 100 (cost unknown), member discount 50
 *   Return  against sale 1: 2 restocked + 1 damaged, 300 refunded
 *   Draft, cancelled and held sales — ignored
 *   Expenses Rent 500, Electricity 150; till cash in/out — ignored
 * Last month:
 *   Sale    Drinks 4 × 100 (cost 25)
 */
function plDataset(): void
{
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $cola = Product::factory()->create(['category_id' => $drinks->id]);
    $loose = Product::factory()->create(['category_id' => null]);

    $sale1 = plInvoice([
        'subtotal' => 1000, 'tax' => 50, 'discount' => 100,
        'courier_charge' => 60, 'courier_cost' => 80,
        'total' => 1010, 'paid_amount' => 1010,
    ]);
    $line = plItem($sale1, $cola, 10, 100, 60);

    $sale2 = plInvoice([
        'status' => 'partial', 'subtotal' => 500, 'membership_discount' => 50,
        'total' => 450, 'paid_amount' => 200, 'due_amount' => 250,
    ]);
    plItem($sale2, $loose, 5, 100, 0);

    $return = plInvoice([
        'type' => 'return', 'parent_invoice_id' => $sale1->id,
        'subtotal' => 300, 'total' => 300, 'paid_amount' => 300,
    ]);
    plItem($return, $cola, 2, 100, 60, ['parent_item_id' => $line->id, 'restock' => true]);
    plItem($return, $cola, 1, 100, 60, ['parent_item_id' => $line->id, 'restock' => false]);
    $sale1->update(['returned_amount' => 300]);

    foreach ([['status' => 'draft'], ['status' => 'cancelled'], ['status' => 'draft', 'is_held' => true]] as $ignored) {
        $inv = plInvoice($ignored + ['subtotal' => 9999, 'total' => 9999]);
        plItem($inv, $cola, 99, 101, 50);
    }

    plExpense('Rent', 500);
    plExpense('Electricity', 150);
    plExpense('Drawer top-up', 700, 'cash_in');
    plExpense('Drawer cash out', 1000, 'cash_out');
    plExpense('Rent', 999)->delete();

    $old = plInvoice([
        'subtotal' => 400, 'total' => 400, 'paid_amount' => 400,
        'invoice_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(4),
    ]);
    plItem($old, $cola, 4, 100, 25);
}

it('builds the profit and loss statement for the period', function () {
    plDataset();

    $current = Livewire::test(ProfitLossReport::class)
        ->assertSet('period', 'this_month')
        ->viewData('current');

    expect($current['invoices'])->toBe(2)
        ->and($current['goods_sales'])->toEqual(1500.0)
        ->and($current['tax'])->toEqual(50.0)
        // Billed for goods: 1010 − 60 courier, plus 450.
        ->and($current['gross_sales'])->toEqual(1400.0)
        ->and($current['returns_incl_tax'])->toEqual(300.0)
        ->and($current['discounts'])->toEqual(150.0)
        ->and($current['returns'])->toEqual(300.0)
        ->and($current['net_sales'])->toEqual(1050.0)
        // 600 sold, minus 2 × 60 restocked; the damaged unit stays a cost.
        ->and($current['cogs'])->toEqual(480.0)
        ->and($current['gross_profit'])->toEqual(570.0)
        ->and($current['courier_margin'])->toEqual(-20.0)
        ->and($current['expense_total'])->toEqual(650.0)
        ->and($current['net_profit'])->toEqual(-100.0)
        ->and($current['unknown_cost_lines'])->toBe(1)
        ->and($current['unknown_cost_sales'])->toEqual(500.0);

    expect(collect($current['expenses'])->pluck('total', 'category')->all())
        ->toEqual(['Rent' => 500.0, 'Electricity' => 150.0]);
});

it('takes a refund off net sales and its tax off tax collected', function () {
    openShift();
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $cola = Product::factory()->create(['category_id' => $drinks->id]);

    // 2 × 50 plus 10% tax, one unit brought back.
    $sale = plInvoice(['warehouse_id' => $this->warehouse->id, 'subtotal' => 100, 'tax' => 10, 'total' => 110, 'paid_amount' => 110]);
    $line = plItem($sale, $cola, 2, 50, 30, ['subtotal' => 110, 'tax_rate' => 10, 'tax_amount' => 10]);

    $return = app(SalesReturnService::class)->process($sale, [$line->id => ['quantity' => 1]], 'cash');

    expect((float) $return->total)->toBe(55.0)
        ->and((float) $return->tax)->toBe(5.0)
        ->and((float) $return->subtotal)->toBe(50.0)
        ->and((float) $return->items->sole()->tax_amount)->toBe(5.0);

    $report = Livewire::test(ProfitLossReport::class);
    $current = $report->viewData('current');

    expect($current['gross_sales'])->toEqual(110.0)
        ->and($current['returns'])->toEqual(50.0)
        ->and($current['tax'])->toEqual(5.0)
        ->and($current['net_sales'])->toEqual(50.0)
        ->and(collect($report->viewData('categories'))->keyBy('category')['Drinks']['sales'])->toEqual(50.0);
});

it('does not count till cash in or out as expenses', function () {
    plExpense('Drawer top-up', 700, 'cash_in');
    plExpense('Drawer cash out', 1000, 'cash_out');

    $current = Livewire::test(ProfitLossReport::class)->viewData('current');

    expect($current['expense_total'])->toEqual(0.0)
        ->and($current['expenses'])->toBe([])
        ->and($current['net_profit'])->toEqual(0.0);
});

it('compares with the previous period', function () {
    plDataset();

    $previous = Livewire::test(ProfitLossReport::class)->viewData('previous');

    expect($previous['net_sales'])->toEqual(400.0)
        ->and($previous['cogs'])->toEqual(100.0)
        ->and($previous['gross_profit'])->toEqual(300.0)
        ->and($previous['net_profit'])->toEqual(300.0);

    expect(ProfitLossReport::change(-100, 300))->toEqual(-133.3)
        ->and(ProfitLossReport::change(100, 0))->toBeNull();
});

it('breaks gross profit down by product category', function () {
    plDataset();

    $rows = collect(Livewire::test(ProfitLossReport::class)->viewData('categories'))->keyBy('category');

    expect($rows['Drinks']['sales'])->toEqual(700.0)
        ->and($rows['Drinks']['cost'])->toEqual(480.0)
        ->and($rows['Drinks']['profit'])->toEqual(220.0)
        ->and($rows['Uncategorised']['sales'])->toEqual(500.0)
        ->and($rows['Uncategorised']['profit'])->toEqual(500.0);
});

it('uses a custom date range', function () {
    plDataset();

    $from = now()->subMonthNoOverflow()->startOfMonth();

    $current = Livewire::test(ProfitLossReport::class)
        ->set('period', 'custom')
        ->set('dateFrom', $from->toDateString())
        ->set('dateTo', $from->copy()->endOfMonth()->toDateString())
        ->viewData('current');

    expect($current['net_sales'])->toEqual(400.0)
        ->and($current['expense_total'])->toEqual(0.0);
});

it('renders the report page', function () {
    plDataset();

    $this->get(route('reports.profit-loss'))
        ->assertOk()
        ->assertSee('Profit &amp; Loss', false)
        ->assertSee('Cost unknown for 1 sold line');
});

it('forbids staff from the profit and loss report', function () {
    $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

    $this->actingAs($staff)->get(route('reports.profit-loss'))->assertForbidden();
});

it('reports what was billed for goods after discounts, tax in and courier out', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $cola = Product::factory()->create(['category_id' => $drinks->id]);

    // 10 × 100 with 15% on top, 100 off the bill: 900 net + 135 tax, and 50 courier.
    $sale = plInvoice([
        'subtotal' => 1000, 'discount' => 100, 'tax' => 135,
        'courier_charge' => 50, 'total' => 1085, 'paid_amount' => 1085,
    ]);
    plItem($sale, $cola, 10, 100, 60, ['subtotal' => 1150, 'tax_rate' => 15, 'tax_amount' => 150]);

    $current = Livewire::test(ProfitLossReport::class)->viewData('current');

    // The old figure, 1000 before the discount + 135 tax after it, was 1135.
    expect($current['gross_sales'])->toEqual(1035.0)
        ->and($current['net_sales'])->toEqual(900.0)
        ->and($current['tax'])->toEqual(135.0);
});

it('nets a fully returned discounted sale to zero in the category breakdown', function () {
    openShift();
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $cola = Product::factory()->create(['category_id' => $drinks->id]);

    // 2 × 100 (cost 60), 20 off the bill: the refund is 180, after the discount.
    $sale = plInvoice(['warehouse_id' => $this->warehouse->id, 'subtotal' => 200, 'discount' => 20, 'total' => 180, 'paid_amount' => 180]);
    $line = plItem($sale, $cola, 2, 100, 60);

    $return = app(SalesReturnService::class)->process($sale, [$line->id => ['quantity' => 2]], 'cash');
    expect((float) $return->total)->toBe(180.0);

    $report = Livewire::test(ProfitLossReport::class);
    $drinksRow = collect($report->viewData('categories'))->keyBy('category')['Drinks'];

    // Sold and returned are both valued before the bill discount (200 − 200).
    // Valuing the return at its 180 refund used to leave 20 of phantom profit.
    expect($drinksRow['sales'])->toEqual(0.0)
        ->and($drinksRow['cost'])->toEqual(0.0)
        ->and($drinksRow['profit'])->toEqual(0.0)
        ->and($drinksRow['qty'])->toEqual(0.0)
        ->and($report->viewData('current')['net_sales'])->toEqual(0.0);
});

it('values a partial return at the sold line price in the category breakdown', function () {
    openShift();
    $drinks = Category::factory()->create(['name' => 'Drinks']);
    $cola = Product::factory()->create(['category_id' => $drinks->id]);

    // 4 × 100 with 15% tax inside (347.83 net for the line), 40 off the bill; one unit back.
    $sale = plInvoice([
        'warehouse_id' => $this->warehouse->id, 'prices_include_tax' => true,
        'subtotal' => 347.83, 'tax' => 46.95, 'discount' => 40, 'total' => 360, 'paid_amount' => 360,
    ]);
    $line = plItem($sale, $cola, 4, 100, 50, ['subtotal' => 400, 'tax_rate' => 15, 'tax_amount' => 52.17]);

    app(SalesReturnService::class)->process($sale, [$line->id => ['quantity' => 1]], 'cash');

    $row = collect(Livewire::test(ProfitLossReport::class)->viewData('categories'))->keyBy('category')['Drinks'];

    // 347.83 sold less a quarter of it, whatever the discounted refund was.
    expect($row['sales'])->toEqual(260.87)
        ->and($row['qty'])->toEqual(3.0)
        ->and($row['cost'])->toEqual(150.0);
});

function plMovement(Product $product, string $type, float $before, float $after, float $unitCost, ?string $notes = null, $at = null): StockMovement
{
    $movement = StockMovement::create([
        'product_id' => $product->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => auth()->id(),
        'type' => $type,
        'quantity' => abs($after - $before),
        'before_quantity' => $before,
        'after_quantity' => $after,
        'unit_cost' => $unitCost,
        'notes' => $notes,
    ]);

    if ($at) {
        $movement->forceFill(['created_at' => $at])->save();
    }

    return $movement;
}

it('takes stock written off out of net profit, net of stock found again', function () {
    $milk = Product::factory()->create();

    plMovement($milk, 'adjustment', 0, 50, 20, 'Opening stock');            // not a gain
    plMovement($milk, 'adjustment', 50, 60, 20, 'Initial stock entry');     // not a gain
    plMovement($milk, 'damaged', 60, 57, 20, 'Damaged goods');              // 60 lost
    plMovement($milk, 'expired', 57, 55, 20, 'Expired batch written off');  // 40 lost
    plMovement($milk, 'adjustment', 55, 51, 20, 'Stock count SC-1');        // 80 short at a count
    plMovement($milk, 'adjustment', 51, 52, 20, 'Found extra stock');       // 20 found again
    plMovement($milk, 'sale', 52, 50, 20);                                  // a sale: COGS, not a loss
    plMovement($milk, 'damaged', 50, 40, 20, 'Damaged goods', now()->subMonthNoOverflow()->startOfMonth()->addDays(2));

    $report = Livewire::test(ProfitLossReport::class);
    $current = $report->viewData('current');

    expect($current['stock_lost'])->toEqual(180.0)
        ->and($current['stock_found'])->toEqual(20.0)
        ->and($current['stock_loss'])->toEqual(160.0)
        ->and($current['net_profit'])->toEqual(-160.0)
        ->and($report->viewData('previous')['stock_loss'])->toEqual(200.0);

    $report->assertSee('Stock written off');
});

it('counts a damaged write-off from the stock adjustment screen at its movement cost', function () {
    $milk = Product::factory()->create(['quantity' => 0, 'cost_price' => 30]);
    app(InventoryService::class)->add($milk->id, $this->warehouse->id, 10, 'purchase', ['unit_cost' => 30]);

    Livewire::test(StockAdjustment::class)
        ->call('selectProduct', $milk->id)
        ->set('adjustment_type', 'remove')
        ->set('quantity', '4')
        ->set('reason', 'Damaged goods')
        ->call('save')
        ->assertHasNoErrors();

    $movement = StockMovement::where('type', 'damaged')->sole();
    $current = Livewire::test(ProfitLossReport::class)->viewData('current');

    expect($movement->quantity)->toBe(4.0)
        ->and($current['stock_loss'])->toEqual(round(4 * (float) $movement->unit_cost, 2))
        ->and($current['stock_loss'])->toBeGreaterThan(0.0)
        ->and($current['net_profit'])->toEqual(-$current['stock_loss']);
});

it('says tax, not VAT, on the report', function () {
    plDataset();

    $this->get(route('reports.profit-loss'))
        ->assertOk()
        ->assertDontSee('VAT')
        ->assertSee('Tax collected')
        ->assertSee('Billed for goods incl. tax, after discounts');
});

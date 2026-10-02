<?php

use App\Livewire\Reports\ProfitLossReport;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
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
        ->and($current['gross_sales'])->toEqual(1550.0)
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

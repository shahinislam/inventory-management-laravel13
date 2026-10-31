<?php

use App\Livewire\Partners\PartnerForm;
use App\Livewire\Partners\PartnershipReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Partner;
use App\Models\PartnerPeriod;
use App\Models\PartnerTransaction;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PartnershipService;
use App\Services\ProfitLossService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

function makeInvoice(array $attrs = []): Invoice
{
    return Invoice::create(array_merge([
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'customer_name' => 'Buyer',
        'status' => 'paid',
        'subtotal' => 1000,
        'tax' => 0,
        'discount' => 0,
        'courier_charge' => 0,
        'courier_cost' => 0,
        'total' => 1000,
        'paid_amount' => 1000,
        'due_amount' => 0,
        'invoice_date' => today(),
    ], $attrs));
}

function makePurchase(array $attrs = []): PurchaseOrder
{
    return PurchaseOrder::create(array_merge([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'received',
        'subtotal' => 400,
        'tax' => 0,
        'discount' => 0,
        'total' => 400,
        'paid_amount' => 400,
        'order_date' => today(),
    ], $attrs));
}

// ---------- profit query ----------

function makeExpense(float $amount, array $attrs = []): Expense
{
    return Expense::create(array_merge([
        'type' => 'expense',
        'category' => 'Rent',
        'amount' => $amount,
        'expense_date' => today(),
        'method' => 'cash',
        'created_by' => User::first()->id,
    ], $attrs));
}

it('shares the profit and loss net profit, not sales less purchases', function () {
    $product = Product::factory()->create();

    // 10 sold at 100 that cost 40 each, 150 tax on top, rent 100. Stock bought
    // this period is not a cost until it sells, so the 5000 purchase is ignored.
    $invoice = makeInvoice(['subtotal' => 1000, 'tax' => 150, 'total' => 1150, 'paid_amount' => 1150]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_id' => $product->id,
        'product_name' => $product->name, 'product_sku' => $product->sku,
        'quantity' => 10, 'unit_price' => 100, 'unit_cost' => 40,
        'tax_rate' => 15, 'tax_amount' => 150, 'subtotal' => 1150,
    ]);
    makePurchase(['total' => 5000]);
    makeExpense(100);

    $summary = app(PartnershipService::class)->summary(today()->format('Y-m-d'), today()->format('Y-m-d'));
    $statement = app(ProfitLossService::class)->statement(today()->startOfDay(), today()->endOfDay());

    // Old cash basis: 1150 (tax counted) − 5000 purchases = −3850.
    expect($summary['total_sales'])->toBe(1000.0)
        ->and($summary['cogs'])->toBe(400.0)
        ->and($summary['expenses'])->toBe(100.0)
        ->and($summary['total_costs'])->toBe(500.0)
        ->and($summary['profit'])->toBe(500.0)
        ->and($summary['profit'])->toBe($statement['net_profit'])
        ->and($summary['total_sales'] - $summary['total_costs'] + $summary['courier_margin'])->toBe($summary['profit']);
});

it('never counts tax collected as profit', function () {
    makeInvoice(['subtotal' => 1000, 'tax' => 150, 'total' => 1150, 'paid_amount' => 1150]);

    $summary = app(PartnershipService::class)->summary(today()->format('Y-m-d'), today()->format('Y-m-d'));

    expect($summary['total_sales'])->toBe(1000.0)
        ->and($summary['profit'])->toBe(1000.0);
});

it('counts unpaid credit sales and stock written off, as the P&L does', function () {
    makeInvoice(['status' => 'sent', 'paid_amount' => 0, 'due_amount' => 1000]);
    makeInvoice(['status' => 'overdue', 'paid_amount' => 0, 'due_amount' => 1000]);
    $product = Product::factory()->create();
    StockMovement::create([
        'product_id' => $product->id, 'warehouse_id' => Warehouse::first()->id, 'created_by' => User::first()->id,
        'type' => 'damaged', 'quantity' => 3, 'before_quantity' => 10, 'after_quantity' => 7, 'unit_cost' => 50,
    ]);

    $summary = app(PartnershipService::class)->summary(today()->format('Y-m-d'), today()->format('Y-m-d'));

    expect($summary['total_sales'])->toBe(2000.0)
        ->and($summary['stock_loss'])->toBe(150.0)
        ->and($summary['total_costs'])->toBe(150.0)
        ->and($summary['profit'])->toBe(1850.0)
        ->and($summary['invoice_count'])->toBe(2);
});

it('leaves profit unmoved by a pass-through courier charge', function () {
    // Goods 1000, delivery charged 100 and costing 100.
    makeInvoice(['total' => 1100, 'courier_charge' => 100, 'courier_cost' => 100]);

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['total_sales'])->toBe(1000.0)
        ->and($summary['courier_margin'])->toBe(0.0)
        ->and($summary['profit'])->toBe(1000.0);
});

it('treats free delivery as a real cost against profit', function () {
    // Goods 1000, nothing charged for delivery, but it cost the shop 100.
    makeInvoice(['total' => 1000, 'courier_charge' => 0, 'courier_cost' => 100]);

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['courier_margin'])->toBe(-100.0)
        ->and($summary['profit'])->toBe(900.0);
});

it('counts a delivery mark-up as profit', function () {
    makeInvoice(['total' => 1150, 'courier_charge' => 150, 'courier_cost' => 100]);

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['courier_margin'])->toBe(50.0)
        ->and($summary['profit'])->toBe(1050.0);
});

it('excludes draft and cancelled invoices from sales', function () {
    makeInvoice(['total' => 1000, 'status' => 'paid']);
    makeInvoice(['total' => 5000, 'status' => 'draft']);
    makeInvoice(['total' => 7000, 'status' => 'cancelled']);

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['total_sales'])->toBe(1000.0);
});

it('excludes soft-deleted invoices from sales', function () {
    makeInvoice(['total' => 1000]);
    makeInvoice(['total' => 5000])->delete();

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['total_sales'])->toBe(1000.0);
});

it('ignores activity outside the date range', function () {
    makeInvoice(['total' => 1000, 'invoice_date' => today()]);
    makeInvoice(['total' => 9000, 'invoice_date' => today()->subMonths(2)]);

    $summary = app(PartnershipService::class)->summary(
        today()->format('Y-m-d'),
        today()->format('Y-m-d'),
    );

    expect($summary['total_sales'])->toBe(1000.0);
});

// ---------- share validation ----------

it('accepts shares that total exactly 100 percent', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 50, 'is_active' => true]);

    Livewire::test(PartnerForm::class)
        ->set('name', 'B')
        ->set('share_percentage', '50')
        ->call('save')
        ->assertHasNoErrors();

    expect(Partner::totalActiveShare())->toBe(100.0);
});

it('rejects a partner whose share would push the total past 100 percent', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 50, 'is_active' => true]);
    Partner::create(['name' => 'B', 'share_percentage' => 50, 'is_active' => true]);

    Livewire::test(PartnerForm::class)
        ->set('name', 'C')
        ->set('share_percentage', '50')
        ->call('save')
        ->assertHasErrors('share_percentage');

    expect(Partner::where('name', 'C')->exists())->toBeFalse();
});

it('measures an edited partner against its new share, not the stored one', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 50, 'is_active' => true]);
    $b = Partner::create(['name' => 'B', 'share_percentage' => 50, 'is_active' => true]);

    // Raising B from 50 to 50 is a no-op and must not read as 150%.
    Livewire::test(PartnerForm::class, ['partner' => $b])
        ->set('share_percentage', '50')
        ->call('save')
        ->assertHasNoErrors();
});

it('ignores inactive partners when totalling shares', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 100, 'is_active' => true]);
    Partner::create(['name' => 'Old', 'share_percentage' => 40, 'is_active' => false]);

    expect(Partner::totalActiveShare())->toBe(100.0);
});

// ---------- report rows ----------

it('splits profit by share and computes each balance', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 50, 'is_active' => true]);
    $b = Partner::create(['name' => 'B', 'share_percentage' => 50, 'is_active' => true]);

    makeInvoice(['total' => 1000]);
    makeExpense(400); // profit 600 → 300 each

    PartnerTransaction::create([
        'partner_id' => $b->id,
        'type' => 'investment',
        'amount' => 5000,
        'transaction_date' => today(),
    ]);
    PartnerTransaction::create([
        'partner_id' => $b->id,
        'type' => 'withdrawal',
        'amount' => 1200,
        'transaction_date' => today(),
    ]);

    $rows = Livewire::test(PartnershipReport::class)
        ->set('period', 'this_month')
        ->viewData('rows');

    $rowB = $rows->firstWhere('partner.id', $b->id);

    expect($rowB['profit_share'])->toBe(300.0)
        ->and($rowB['invested'])->toBe(5000.0)
        ->and($rowB['withdrawn'])->toBe(1200.0)
        // 5000 in + 300 earned − 1200 taken out
        ->and($rowB['balance'])->toBe(4100.0);
});

// ---------- period close ----------

it('locks profit and shares when a period is closed', function () {
    $a = Partner::create(['name' => 'A', 'share_percentage' => 50, 'is_active' => true]);
    Partner::create(['name' => 'B', 'share_percentage' => 50, 'is_active' => true]);

    $invoice = makeInvoice(['total' => 1000]);

    Livewire::test(PartnershipReport::class)
        ->set('period', 'this_month')
        ->call('closePeriod');

    $period = PartnerPeriod::first();

    expect($period)->not->toBeNull()
        ->and((float) $period->profit)->toBe(1000.0)
        ->and($period->shares)->toHaveCount(2)
        ->and((float) $period->shares->firstWhere('partner_id', $a->id)->profit_share)->toBe(500.0);

    // Editing the underlying invoice must not move the settled figure.
    $invoice->update(['total' => 9999, 'subtotal' => 9999]);

    expect((float) $period->fresh()->profit)->toBe(1000.0);
});

it('locks the period costs, not its purchases, when closing', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 100, 'is_active' => true]);
    makeInvoice(['total' => 1000]);
    makePurchase(['total' => 400]);
    makeExpense(250);

    Livewire::test(PartnershipReport::class)
        ->set('period', 'this_month')
        ->assertSee('Net profit')
        ->assertSee('Costs')
        ->assertDontSee('Cash basis')
        ->call('closePeriod');

    $period = PartnerPeriod::sole();

    expect((float) $period->total_sales)->toBe(1000.0)
        ->and((float) $period->total_purchases)->toBe(250.0)
        ->and((float) $period->profit)->toBe(750.0)
        ->and((float) $period->shares->sole()->profit_share)->toBe(750.0);
});

it('refuses to close the same period twice', function () {
    Partner::create(['name' => 'A', 'share_percentage' => 100, 'is_active' => true]);
    makeInvoice(['total' => 1000]);

    Livewire::test(PartnershipReport::class)
        ->set('period', 'this_month')
        ->call('closePeriod')
        ->call('closePeriod');

    expect(PartnerPeriod::count())->toBe(1);
});

it('refuses to close a period with no active partners', function () {
    makeInvoice(['total' => 1000]);

    Livewire::test(PartnershipReport::class)
        ->set('period', 'this_month')
        ->call('closePeriod');

    expect(PartnerPeriod::count())->toBe(0);
});

// ---------- access control ----------

it('keeps non-admins out of the partnership pages', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);

    $this->actingAs($user)->get(route('partners.index'))->assertForbidden();
    $this->actingAs($user)->get(route('partners.report'))->assertForbidden();
    $this->actingAs($user)->get(route('partners.transactions'))->assertForbidden();
})->with(['manager', 'staff', 'viewer']);

it('lets an admin reach the partnership pages', function () {
    $this->get(route('partners.index'))->assertOk();
    $this->get(route('partners.report'))->assertOk();
    $this->get(route('partners.transactions'))->assertOk();
});

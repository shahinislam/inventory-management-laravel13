<?php

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Invoices\InvoiceList;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesReturnService;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

/** Issued and non-issued sales dated today; issued ones bill 1000 in all, 100 brought back. */
function dashboardSales(): void
{
    foreach ([
        ['status' => 'paid', 'total' => 300, 'paid_amount' => 300],
        ['status' => 'sent', 'total' => 200, 'due_amount' => 200],
        ['status' => 'overdue', 'total' => 100, 'due_amount' => 100],
        ['status' => 'partial', 'total' => 400, 'paid_amount' => 150, 'due_amount' => 150, 'returned_amount' => 100],
        ['status' => 'draft', 'total' => 5000, 'due_amount' => 5000],
        ['status' => 'cancelled', 'total' => 5000],
        ['status' => 'draft', 'is_held' => true, 'total' => 5000],
        ['type' => 'return', 'status' => 'paid', 'total' => 100],
    ] as $attrs) {
        Invoice::create($attrs + [
            'warehouse_id' => Warehouse::first()->id,
            'created_by' => auth()->id(),
            'customer_name' => 'Buyer',
            'invoice_date' => today(),
        ]);
    }
}

it('counts every issued sale on the dashboard, less returns', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    Warehouse::factory()->default()->create();
    dashboardSales();

    $c = Livewire::test(DashboardIndex::class);
    $stats = $c->viewData('stats');

    // Paid-only used to show 300 and ignore the 100 returned.
    expect($stats['totalSales'])->toEqual(900.0)
        ->and($stats['totalInvoices'])->toBe(4)
        ->and($stats['totalRevenue'])->toEqual(900.0)
        ->and(collect($c->viewData('salesChart'))->last()['sales'])->toEqual(900.0);
});

it('updates the dashboard after a return, without waiting for the cache', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    $warehouse = Warehouse::factory()->default()->create();
    openShift();

    $product = Product::factory()->create();
    $sale = Invoice::create([
        'warehouse_id' => $warehouse->id, 'created_by' => auth()->id(), 'customer_name' => 'Buyer',
        'status' => 'sent', 'subtotal' => 500, 'total' => 500, 'due_amount' => 500, 'invoice_date' => today(),
    ]);
    $line = $sale->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku,
        'quantity' => 5, 'unit_price' => 100, 'subtotal' => 500,
    ]);

    expect(Livewire::test(DashboardIndex::class)->viewData('stats')['totalSales'])->toEqual(500.0);

    // The return takes 200 off the due; the dashboard cache is flushed by the return.
    app(SalesReturnService::class)->process($sale, [$line->id => ['quantity' => 2]], 'cash');

    expect(Livewire::test(DashboardIndex::class)->viewData('stats')['totalSales'])->toEqual(300.0);
});

it('sums today and pending on the invoice list from issued sales only', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    Warehouse::factory()->default()->create();
    dashboardSales();

    $summary = Livewire::test(InvoiceList::class)->viewData('summary');

    // Pending used to include the 5000 draft and leave out the overdue 100.
    expect((float) $summary['total_today'])->toEqual(900.0)
        ->and((float) $summary['total_pending'])->toEqual(450.0)
        ->and($summary['total_overdue'])->toBe(1);
});

<?php

use App\Livewire\Reports\SalesReport;
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

function srInvoice(array $attrs): Invoice
{
    return Invoice::create($attrs + [
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => auth()->id(),
        'customer_name' => 'Buyer',
        'status' => 'paid',
        'invoice_date' => today(),
    ]);
}

function srLine(Invoice $invoice, Product $product, float $qty, float $subtotal, float $tax = 0, array $extra = []): InvoiceItem
{
    return InvoiceItem::create($extra + [
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => $qty,
        'unit_price' => round($subtotal / $qty, 2),
        'subtotal' => $subtotal,
        'tax_amount' => $tax,
    ]);
}

it('counts every issued sale, including unpaid credit sales', function () {
    srInvoice(['status' => 'paid', 'total' => 100, 'paid_amount' => 100]);
    srInvoice(['status' => 'sent', 'total' => 200, 'due_amount' => 200]);
    srInvoice(['status' => 'overdue', 'total' => 300, 'due_amount' => 300]);
    srInvoice(['status' => 'partial', 'total' => 400, 'paid_amount' => 150, 'due_amount' => 250]);
    srInvoice(['status' => 'returned', 'total' => 50, 'returned_amount' => 50]);
    // Never counted: not issued, called off, parked, or a return note.
    srInvoice(['status' => 'draft', 'total' => 9000]);
    srInvoice(['status' => 'cancelled', 'total' => 9000]);
    srInvoice(['status' => 'draft', 'is_held' => true, 'total' => 9000]);
    srInvoice(['type' => 'return', 'total' => 9000]);

    $summary = Livewire::test(SalesReport::class)->viewData('summary');

    // 100 + 200 + 300 + 400 + (50 − 50). Paid/partial/returned only used to give 500.
    expect((float) $summary['total_sales'])->toEqual(1000.0)
        ->and($summary['total_invoices'])->toBe(5)
        ->and((float) $summary['total_paid'])->toEqual(250.0)
        ->and((float) $summary['total_due'])->toEqual(750.0)
        ->and((float) $summary['avg_invoice'])->toEqual(200.0);
});

it('ranks top products by goods value excluding tax and returns', function () {
    $soap = Product::factory()->create(['name' => 'Soap']);
    $rice = Product::factory()->create(['name' => 'Rice']);

    // Soap: 4 at 115 with 15% tax inside = 400 net. One comes back.
    $sale = srInvoice(['status' => 'sent', 'prices_include_tax' => true, 'subtotal' => 400, 'tax' => 60, 'total' => 460]);
    $soapLine = srLine($sale, $soap, 4, 460, 60);

    // Rice: 3 at 100 with 10% added on top = 300 net.
    $other = srInvoice(['subtotal' => 300, 'tax' => 30, 'total' => 330, 'paid_amount' => 330]);
    srLine($other, $rice, 3, 330, 30);

    $return = srInvoice(['type' => 'return', 'parent_invoice_id' => $sale->id, 'subtotal' => 100, 'tax' => 15, 'total' => 115]);
    srLine($return, $soap, 1, 115, 15, ['parent_item_id' => $soapLine->id]);
    $sale->update(['returned_amount' => 115]);

    $top = Livewire::test(SalesReport::class)->viewData('topProducts')->keyBy('product_name');

    // Gross line totals with tax used to rank soap at 460 and rice at 330.
    expect((float) $top['Soap']->total_revenue)->toEqual(300.0)
        ->and((float) $top['Soap']->total_qty)->toEqual(3.0)
        ->and((float) $top['Rice']->total_revenue)->toEqual(300.0)
        ->and((float) $top['Rice']->total_qty)->toEqual(3.0);
});

it('drops a fully returned product from the top products', function () {
    $soap = Product::factory()->create(['name' => 'Soap']);
    $sale = srInvoice(['status' => 'returned', 'subtotal' => 200, 'total' => 200, 'returned_amount' => 200]);
    $line = srLine($sale, $soap, 2, 200);

    $return = srInvoice(['type' => 'return', 'parent_invoice_id' => $sale->id, 'subtotal' => 200, 'total' => 200]);
    srLine($return, $soap, 2, 200, 0, ['parent_item_id' => $line->id]);

    expect(Livewire::test(SalesReport::class)->viewData('topProducts'))->toHaveCount(0);
});

it('labels the billed total so it does not clash with net sales on the P&L', function () {
    srInvoice(['status' => 'sent', 'total' => 200, 'due_amount' => 200]);

    $this->get(route('reports.sales'))
        ->assertOk()
        ->assertSee('Sales incl. tax')
        ->assertSee('Sales excl. tax')
        ->assertDontSee('Net Sales');
});

<?php

/**
 * A supplier's balance is worked out from its purchase orders: what is owed
 * on every placed order, less any credit the supplier holds for the shop.
 * suppliers.current_balance is never written, so it is not shown.
 */

use App\Livewire\Suppliers\SupplierList;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

function balanceTestOrder(Supplier $supplier, array $attributes): PurchaseOrder
{
    return PurchaseOrder::create($attributes + [
        'supplier_id' => $supplier->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'ordered',
        'order_date' => today(),
    ]);
}

it('adds up what is owed on placed orders only', function () {
    $supplier = Supplier::factory()->create(['current_balance' => 999]);

    balanceTestOrder($supplier, ['subtotal' => 300, 'total' => 300, 'paid_amount' => 100]);                  // owes 200
    balanceTestOrder($supplier, ['subtotal' => 100, 'total' => 100, 'returned_amount' => 30, 'status' => 'received']); // owes 70
    balanceTestOrder($supplier, ['subtotal' => 500, 'total' => 500, 'status' => 'draft']);                    // not placed
    balanceTestOrder($supplier, ['subtotal' => 400, 'total' => 400, 'status' => 'cancelled']);                // not owed
    balanceTestOrder($supplier, ['type' => 'return', 'subtotal' => 30, 'total' => 30, 'status' => 'received']); // counted on its parent

    expect($supplier->balance)->toBe(270.0)
        ->and(Supplier::withBalance()->find($supplier->id)->balance)->toBe(270.0);

    Livewire::test(SupplierList::class)
        ->assertSee(money(270))
        ->assertDontSee(money(999));
});

it('shows a supplier holding the shop\'s money as credit', function () {
    // Paid 50 on a 100 order, then 60 of goods went back without a refund.
    $supplier = Supplier::factory()->create();
    balanceTestOrder($supplier, ['subtotal' => 100, 'total' => 100, 'paid_amount' => 50, 'returned_amount' => 60, 'status' => 'received']);

    // A supplier still owed 25 shows as owed.
    $other = Supplier::factory()->create();
    balanceTestOrder($other, ['subtotal' => 25, 'total' => 25]);

    expect($supplier->balance)->toBe(-10.0)
        ->and($other->balance)->toBe(25.0);

    Livewire::test(SupplierList::class)
        ->assertSee(money(10).' credit')
        ->assertSee(money(25));
});

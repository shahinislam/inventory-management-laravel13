<?php

use App\Livewire\Invoices\InvoiceView;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Settings\UserManagement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

// ------------------------------------------------------------ last-admin lock

it('refuses to delete the only active admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $this->actingAs($other);

    Livewire::test(UserManagement::class)
        ->set('deleteId', $admin->id)
        ->call('delete');

    expect(User::find($admin->id))->not->toBeNull();
});

it('allows deleting an admin when another active admin remains', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $spare = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($spare);

    Livewire::test(UserManagement::class)
        ->set('deleteId', $admin->id)
        ->call('delete');

    expect(User::find($admin->id))->toBeNull();
});

it('refuses to deactivate the only active admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $this->actingAs($other);

    Livewire::test(UserManagement::class)->call('toggleStatus', $admin->id);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('refuses to demote the only active admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $this->actingAs($other);

    Livewire::test(UserManagement::class)
        ->call('openEditModal', $admin->id)
        ->set('role', 'staff')
        ->call('save');

    expect($admin->fresh()->role)->toBe('admin');
});

it('refuses to delete your own account', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->set('deleteId', $admin->id)
        ->call('delete');

    expect(User::find($admin->id))->not->toBeNull();
});

// ------------------------------------------------------- purchase order state

function orderInState(string $status, User $user): PurchaseOrder
{
    $order = PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'created_by' => $user->id,
        'status' => $status,
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'order_date' => now(),
    ]);

    $order->items()->create([
        'product_id' => Product::factory()->create()->id,
        'quantity' => 1,
        'unit_cost' => 100,
        'subtotal' => 100,
    ]);

    return $order;
}

it('blocks staff from approving a purchase order', function () {
    $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
    $order = orderInState('pending', $staff);

    $this->actingAs($staff);

    Livewire::test(PurchaseForm::class, ['order' => $order])->call('approve');

    expect($order->fresh()->status)->toBe('pending');
});

it('lets a manager approve a pending order', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $order = orderInState('pending', $manager);

    $this->actingAs($manager);

    Livewire::test(PurchaseForm::class, ['order' => $order])->call('approve');

    expect($order->fresh()->status)->toBe('approved');
});

it('refuses to approve an already received order', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $order = orderInState('received', $admin);

    $this->actingAs($admin);

    Livewire::test(PurchaseForm::class, ['order' => $order])->call('approve');

    expect($order->fresh()->status)->toBe('received');
});

it('refuses to mark a draft order as ordered', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $order = orderInState('draft', $admin);

    $this->actingAs($admin);

    Livewire::test(PurchaseForm::class, ['order' => $order])->call('markAsOrdered');

    expect($order->fresh()->status)->toBe('draft');
});

it('refuses to cancel a received order', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $order = orderInState('received', $admin);

    $this->actingAs($admin);

    Livewire::test(PurchaseForm::class, ['order' => $order])->call('cancel');

    expect($order->fresh()->status)->toBe('received');
});

// ------------------------------------------------------------ invoice payment

function unpaidInvoice(User $user, float $total = 1000): Invoice
{
    return Invoice::create([
        'created_by' => $user->id,
        'customer_name' => 'Test',
        'status' => 'sent',
        'subtotal' => $total,
        'tax' => 0,
        'discount' => 0,
        'total' => $total,
        'paid_amount' => 0,
        'due_amount' => $total,
        'invoice_date' => now(),
    ]);
}

it('records a partial payment and leaves the invoice partial', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $invoice = unpaidInvoice($admin, 1000);

    $this->actingAs($admin);

    Livewire::test(InvoiceView::class, ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('payment_amount', '400')
        ->call('recordPayment');

    $invoice->refresh();

    expect((float) $invoice->paid_amount)->toBe(400.0)
        ->and((float) $invoice->due_amount)->toBe(600.0)
        ->and($invoice->status)->toBe('partial');
});

it('marks the invoice paid when the balance is cleared', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $invoice = unpaidInvoice($admin, 1000);

    $this->actingAs($admin);

    Livewire::test(InvoiceView::class, ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->call('recordPayment');

    $invoice->refresh();

    expect((float) $invoice->due_amount)->toBe(0.0)
        ->and($invoice->status)->toBe('paid');
});

it('rejects a payment larger than the outstanding balance', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $invoice = unpaidInvoice($admin, 1000);

    $this->actingAs($admin);

    Livewire::test(InvoiceView::class, ['invoice' => $invoice])
        ->set('payment_amount', '1500')
        ->call('recordPayment')
        ->assertHasErrors('payment_amount');

    expect(Payment::count())->toBe(0)
        ->and((float) $invoice->fresh()->paid_amount)->toBe(0.0);
});

it('accepts a payment amount typed with a thousands separator', function () {
    // The prefilled value for a 1000+ invoice used to contain a comma, which
    // failed numeric validation.
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $invoice = unpaidInvoice($admin, 1500);

    $this->actingAs($admin);

    Livewire::test(InvoiceView::class, ['invoice' => $invoice])
        ->set('payment_amount', '1,500.00')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect((float) $invoice->fresh()->due_amount)->toBe(0.0);
});

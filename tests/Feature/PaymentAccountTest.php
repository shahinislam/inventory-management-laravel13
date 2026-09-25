<?php

use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Invoices\InvoiceView;
use App\Livewire\PaymentAccounts\PaymentAccountForm;
use App\Livewire\PaymentAccounts\PaymentAccountList;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Reports\DueReport;
use App\Livewire\Reports\PaymentAccountReport;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->admin);

    $this->card = PaymentAccount::create(['name' => 'Company Visa', 'type' => 'card', 'account_number' => '4111111111111234']);
    $this->bank = PaymentAccount::create(['name' => 'City Bank', 'type' => 'bank', 'bank_name' => 'City Bank']);
});

function placedOrder(float $total = 1000, float $paid = 0): PurchaseOrder
{
    return PurchaseOrder::create([
        'supplier_id' => Supplier::factory()->create()->id,
        'warehouse_id' => Warehouse::first()->id,
        'created_by' => User::first()->id,
        'status' => 'ordered',
        'subtotal' => $total,
        'total' => $total,
        'paid_amount' => $paid,
        'order_date' => today(),
    ]);
}

// ============ ACCOUNTS CRUD ============

it('creates a payment account', function () {
    Livewire::test(PaymentAccountForm::class)
        ->set('name', 'bKash Merchant')
        ->set('type', 'mobile_wallet')
        ->set('account_number', '01711000000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('payment-accounts.index'));

    expect(PaymentAccount::where('name', 'bKash Merchant')->first())
        ->type->toBe('mobile_wallet')
        ->display_name->toBe('bKash Merchant ••0000');
});

it('toggles and soft-deletes an account', function () {
    Livewire::test(PaymentAccountList::class)
        ->call('toggleStatus', $this->card->id)
        ->call('confirmDelete', $this->bank->id)
        ->call('delete');

    expect($this->card->fresh()->is_active)->toBeFalse()
        ->and(PaymentAccount::withTrashed()->find($this->bank->id)->trashed())->toBeTrue();
});

it('lets viewers see the list but not the create form', function () {
    $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

    $this->actingAs($viewer)->get(route('payment-accounts.index'))->assertOk();
    $this->actingAs($viewer)->get(route('payment-accounts.create'))->assertForbidden();
});

it('offers only matching, active accounts per method', function () {
    PaymentAccount::create(['name' => 'Old Card', 'type' => 'card', 'is_active' => false]);

    expect(PaymentAccount::forMethod('card')->pluck('id')->all())->toBe([$this->card->id])
        ->and(PaymentAccount::forMethod('bank_transfer')->pluck('id')->all())->toBe([$this->bank->id])
        ->and(PaymentAccount::forMethod('cash')->count())->toBe(0);
});

// ============ POS ============

it('requires an account for a card sale at the POS', function () {
    PaymentAccount::create(['name' => 'Second Card', 'type' => 'card']); // no auto-select
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('setPaymentMethod', 'card')
        ->call('completeSale')
        ->assertHasErrors(['paymentAccountId' => 'required']);

    expect(Invoice::count())->toBe(0);
});

it('rejects an account of the wrong type', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('setPaymentMethod', 'card')
        ->set('paymentAccountId', $this->bank->id)
        ->call('completeSale')
        ->assertHasErrors('paymentAccountId');
});

it('tags a card sale with the account and snapshots its details', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'tax_rate' => 0, 'discount' => 0, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('setPaymentMethod', 'card')
        ->assertSet('paymentAccountId', $this->card->id) // only card → pre-selected
        ->call('completeSale')
        ->assertHasNoErrors();

    expect(Payment::first())
        ->payment_account_id->toBe($this->card->id)
        ->account_number->toBe('4111111111111234');
});

it('needs no account for a cash sale', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale')
        ->assertHasNoErrors();

    expect(Payment::first()->payment_account_id)->toBeNull();
});

// ============ POS DUE SALE ============

it('requires a customer for a due sale', function () {
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->set('payLater', true)
        ->set('paidNow', '40')
        ->call('completeSale')
        ->assertHasErrors('customer_id');

    expect(Invoice::count())->toBe(0);
});

it('records a partial due sale', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 100, 'tax_rate' => 0, 'discount' => 0, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('selectCustomer', $customer->id)
        ->call('openPaymentModal')
        ->set('payLater', true)
        ->set('paidNow', '40')
        ->call('completeSale')
        ->assertHasNoErrors();

    $invoice = Invoice::first();
    expect($invoice->status)->toBe('partial')
        ->and((float) $invoice->paid_amount)->toBe(40.0)
        ->and((float) $invoice->due_amount)->toBe(60.0)
        ->and($invoice->paid_date)->toBeNull()
        ->and((float) Payment::sum('amount'))->toBe(40.0);
});

it('records a full due sale without a payment row', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 100, 'tax_rate' => 0, 'discount' => 0, 'quantity' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('selectCustomer', $customer->id)
        ->call('openPaymentModal')
        ->set('payLater', true)
        ->set('paidNow', '0')
        ->call('completeSale')
        ->assertHasNoErrors();

    expect(Invoice::first())
        ->status->toBe('sent')
        ->and((float) Invoice::first()->due_amount)->toBe(100.0)
        ->and(Payment::count())->toBe(0);
});

// ============ INVOICES ============

it('requires an account when an invoice is marked paid by bank', function () {
    PaymentAccount::create(['name' => 'Other Bank', 'type' => 'bank']);
    $product = Product::factory()->create(['selling_price' => 100, 'quantity' => 10]);

    Livewire::test(InvoiceForm::class)
        ->set('customer_name', 'Test')
        ->call('addProduct', $product->id)
        ->set('payment_method', 'bank_transfer')
        ->call('saveAsPaid')
        ->assertHasErrors('payment_account_id');
});

it('records a due payment on an invoice through an account', function () {
    $invoice = Invoice::create([
        'created_by' => $this->admin->id,
        'customer_name' => 'Due Customer',
        'status' => 'sent',
        'total' => 500,
        'paid_amount' => 0,
        'due_amount' => 500,
        'invoice_date' => today(),
    ]);

    Livewire::test(InvoiceView::class, ['invoice' => $invoice])
        ->set('payment_amount', '200')
        ->set('payment_method', 'bank_transfer')
        ->assertSet('payment_account_id', $this->bank->id)
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect($invoice->fresh())
        ->status->toBe('partial')
        ->and((float) $invoice->fresh()->due_amount)->toBe(300.0)
        ->and(Payment::first()->payment_account_id)->toBe($this->bank->id);
});

// ============ PURCHASE PAYMENTS ============

it('records partial and full supplier payments', function () {
    $order = placedOrder(1000);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openPaymentModal')
        ->set('payment_amount', '400')
        ->set('payment_method', 'bank_transfer')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect((float) $order->fresh()->paid_amount)->toBe(400.0)
        ->and(PurchasePayment::first()->payment_account_id)->toBe($this->bank->id);

    Livewire::test(PurchaseForm::class, ['order' => $order->fresh()])
        ->call('openPaymentModal')
        ->set('payment_amount', '600')
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(PurchasePayment::count())->toBe(2);
});

it('rejects overpaying a supplier', function () {
    $order = placedOrder(1000, 900);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->call('openPaymentModal')
        ->set('payment_amount', '200')
        ->call('recordPayment')
        ->assertHasErrors('payment_amount');

    expect((float) $order->fresh()->paid_amount)->toBe(900.0);
});

it('blocks payments on draft orders', function () {
    $order = placedOrder(1000);
    $order->update(['status' => 'draft']);

    Livewire::test(PurchaseForm::class, ['order' => $order])
        ->set('payment_amount', '100')
        ->call('recordPayment');

    expect(PurchasePayment::count())->toBe(0);
});

// ============ REPORTS ============

it('lists customer and supplier dues', function () {
    Invoice::create([
        'created_by' => $this->admin->id, 'customer_name' => 'Owes Money', 'status' => 'partial',
        'total' => 500, 'paid_amount' => 200, 'due_amount' => 300, 'invoice_date' => today(),
        'due_date' => today()->subDays(5),
    ]);
    Invoice::create([
        'created_by' => $this->admin->id, 'customer_name' => 'Draft Only', 'status' => 'draft',
        'total' => 999, 'paid_amount' => 0, 'due_amount' => 999, 'invoice_date' => today(),
    ]);
    placedOrder(1000, 250);

    $component = Livewire::test(DueReport::class)
        ->assertSee('Owes Money')
        ->assertDontSee('Draft Only');

    expect($component->viewData('summary'))
        ->receivable->toBe(300.0)
        ->receivable_overdue->toBe(300.0)
        ->payable->toBe(750.0);

    $component->set('tab', 'suppliers')->assertViewHas('filteredTotal', 750.0);
});

it('totals money in and out per account', function () {
    $invoice = Invoice::create([
        'created_by' => $this->admin->id, 'customer_name' => 'X', 'status' => 'paid',
        'total' => 300, 'paid_amount' => 300, 'due_amount' => 0, 'invoice_date' => today(),
    ]);
    Payment::create([
        'invoice_id' => $invoice->id, 'created_by' => $this->admin->id, 'payment_account_id' => $this->bank->id,
        'amount' => 300, 'method' => 'bank_transfer', 'status' => 'completed', 'payment_date' => today(),
    ]);
    PurchasePayment::create([
        'purchase_order_id' => placedOrder()->id, 'created_by' => $this->admin->id, 'payment_account_id' => $this->bank->id,
        'amount' => 120, 'method' => 'bank_transfer', 'payment_date' => today(),
    ]);

    $component = Livewire::test(PaymentAccountReport::class);

    expect($component->viewData('summary'))->toBe(['in' => 300.0, 'out' => 120.0, 'net' => 180.0]);

    $bankRow = $component->viewData('accounts')->firstWhere('account.id', $this->bank->id);
    expect($bankRow->in)->toBe(300.0)->and($bankRow->out)->toBe(120.0)
        ->and($component->viewData('transactions')->total())->toBe(2);
});

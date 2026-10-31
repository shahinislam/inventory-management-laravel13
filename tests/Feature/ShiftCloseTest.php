<?php

use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseList;
use App\Livewire\Shifts\ShiftPanel;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShiftService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

/** A till sale on the shift: $paid of $total taken in cash, the rest left on due. */
function shiftSale(CashShift $shift, float $total, float $paid): Invoice
{
    $sale = Invoice::create([
        'warehouse_id' => $shift->warehouse_id, 'created_by' => auth()->id(), 'shift_id' => $shift->id,
        'customer_name' => 'Buyer', 'status' => $paid >= $total ? 'paid' : 'partial',
        'subtotal' => $total, 'total' => $total, 'paid_amount' => $paid, 'due_amount' => $total - $paid,
        'invoice_date' => today(),
    ]);

    if ($paid > 0) {
        Payment::create([
            'invoice_id' => $sale->id, 'created_by' => auth()->id(), 'shift_id' => $shift->id,
            'amount' => $paid, 'method' => 'cash', 'status' => 'completed', 'payment_date' => today(),
        ]);
    }

    return $sale;
}

function tillExpense(CashShift $shift, float $amount): Expense
{
    return Expense::create([
        'type' => 'expense', 'category' => 'Cleaning', 'amount' => $amount, 'expense_date' => today(),
        'method' => 'cash', 'shift_id' => $shift->id, 'created_by' => auth()->id(),
    ]);
}

it('keeps showing the expected cash stored at close', function () {
    $shift = openShift(1000);
    shiftSale($shift, 500, 500);
    $expense = tillExpense($shift, 100);

    $shift = app(ShiftService::class)->close($shift, 1390);
    expect((float) $shift->expected_cash)->toBe(1400.0)
        ->and((float) $shift->difference)->toBe(-10.0);

    // Something linked to the shift changes after the count (here, behind the
    // screens' back): the live lines move, the stored figure must not.
    $expense->forceFill(['amount' => 40])->save();

    $summary = app(ShiftService::class)->summary($shift->fresh());

    expect($summary['closed'])->toBeTrue()
        ->and($summary['expected_cash'])->toBe(1400.0)
        ->and($summary['drawer_total'])->toBe(1460.0);

    Livewire::test(ShiftPanel::class, ['shift' => $shift->fresh()])
        ->assertSee('Expected at close')
        ->assertSee(money(1400))
        ->assertSee('changed after it was closed')
        ->assertSee(money(1390));

    $this->get(route('shifts.report', $shift))
        ->assertOk()
        ->assertSee('EXPECTED AT CLOSE')
        ->assertSee(money(1400, false))
        ->assertSee('changed after close');
});

it('still works out the expected cash live while the shift is open', function () {
    $shift = openShift(1000);
    shiftSale($shift, 500, 500);
    tillExpense($shift, 100);

    $summary = app(ShiftService::class)->summary($shift);

    expect($summary['closed'])->toBeFalse()
        ->and($summary['expected_cash'])->toBe(1400.0);

    Livewire::test(ShiftPanel::class)->assertSee('Should be in drawer')->assertDontSee('Expected at close');
});

it('shows what was given on due at close, not what is owed today', function () {
    $shift = openShift(0);
    $sale = shiftSale($shift, 500, 200);       // 300 left on due
    shiftSale($shift, 100, 100);               // paid in full

    app(ShiftService::class)->close($shift, 300);

    // Later the customer pays the rest by cheque and the due drops to zero.
    $this->travel(10)->minutes();
    Payment::create([
        'invoice_id' => $sale->id, 'created_by' => auth()->id(),
        'amount' => 300, 'method' => 'cheque', 'status' => 'completed', 'payment_date' => today(),
    ]);
    $sale->update(['paid_amount' => 500, 'due_amount' => 0, 'status' => 'paid']);

    $summary = app(ShiftService::class)->summary($shift->fresh());

    expect($summary['due_given'])->toBe(300.0);

    $this->get(route('shifts.report', $shift))
        ->assertOk()
        ->assertSeeInOrder(['Given on due', money(300, false), 'Returns']);
});

it('counts a return made before the close against the due at close', function () {
    $shift = openShift(0);
    $sale = shiftSale($shift, 500, 0);

    // 120 brought back during the shift and taken off the due (nothing refunded).
    Invoice::create([
        'type' => 'return', 'parent_invoice_id' => $sale->id, 'shift_id' => $shift->id,
        'warehouse_id' => $this->warehouse->id, 'created_by' => auth()->id(), 'customer_name' => 'Buyer',
        'status' => 'paid', 'prices_include_tax' => true, 'subtotal' => 120, 'total' => 120, 'paid_amount' => 0,
        'invoice_date' => today(),
    ]);
    $sale->update(['returned_amount' => 120, 'due_amount' => 380]);

    expect(app(ShiftService::class)->summary($shift)['due_given'])->toBe(380.0);

    $closed = app(ShiftService::class)->close($shift, 0);

    expect(app(ShiftService::class)->summary($closed)['due_given'])->toBe(380.0);
});

// ---------- expenses paid from a closed till ----------

it('refuses to edit an expense paid from a closed shift', function () {
    $shift = openShift(1000);
    $expense = tillExpense($shift, 100);
    app(ShiftService::class)->close($shift, 900);

    Livewire::test(ExpenseForm::class, ['expense' => $expense])
        ->assertRedirect(route('expenses.index'));

    expect(session('error'))->toContain($shift->shift_number)
        ->toContain('can no longer be edited or deleted');

    $this->get(route('expenses.edit', $expense))->assertRedirect(route('expenses.index'));

    expect((float) $expense->fresh()->amount)->toBe(100.0);
});

it('refuses to save an expense whose shift closed while it was being edited', function () {
    $shift = openShift(1000);
    $expense = tillExpense($shift, 100);

    $form = Livewire::test(ExpenseForm::class, ['expense' => $expense])->assertNoRedirect();

    app(ShiftService::class)->close($shift, 900);

    $form->set('amount', '250')->call('save')->assertRedirect(route('expenses.index'));

    expect((float) $expense->fresh()->amount)->toBe(100.0)
        ->and(session('error'))->toContain('closed');
});

it('refuses to delete an expense paid from a closed shift', function () {
    $shift = openShift(1000);
    $expense = tillExpense($shift, 100);
    app(ShiftService::class)->close($shift, 900);

    Livewire::test(ExpenseList::class)
        ->assertSee('Shift closed')
        ->call('confirmDelete', $expense->id)
        ->call('delete')
        ->assertSee('can no longer be edited or deleted');

    expect(Expense::find($expense->id))->not->toBeNull();
});

it('still lets an expense on an open shift be edited and deleted', function () {
    $shift = openShift(1000);
    $expense = tillExpense($shift, 100);

    Livewire::test(ExpenseForm::class, ['expense' => $expense])
        ->set('amount', '150')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $expense->fresh()->amount)->toBe(150.0)
        ->and($expense->fresh()->shift_id)->toBe($shift->id);

    Livewire::test(ExpenseList::class)->call('confirmDelete', $expense->id)->call('delete');

    expect(Expense::find($expense->id))->toBeNull();
});

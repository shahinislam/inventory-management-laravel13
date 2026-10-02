<?php

use App\Livewire\Expenses\ExpenseForm;
use App\Livewire\Expenses\ExpenseList;
use App\Models\Expense;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShiftService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

it('requires a category and an amount above zero', function () {
    Livewire::test(ExpenseForm::class)
        ->call('save')
        ->assertHasErrors(['category' => 'required', 'amount' => 'required']);

    Livewire::test(ExpenseForm::class)
        ->set('category', 'Rent')
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['amount']);

    expect(Expense::count())->toBe(0);
});

it('requires a payment account for card expenses', function () {
    Livewire::test(ExpenseForm::class)
        ->set('category', 'Internet & Phone')
        ->set('amount', '1200')
        ->set('method', 'card')
        ->call('save')
        ->assertHasErrors(['payment_account_id' => 'required']);
});

it('requires a transaction id for bKash / Nagad expenses', function () {
    $wallet = PaymentAccount::create(['name' => 'Shop bKash', 'type' => 'mobile_wallet', 'is_active' => true]);

    Livewire::test(ExpenseForm::class)
        ->set('category', 'Transport')
        ->set('amount', '300')
        ->set('method', 'mobile_banking')
        ->set('payment_account_id', $wallet->id)
        ->call('save')
        ->assertHasErrors(['reference' => 'required']);
});

it('records an expense paid from a bank account', function () {
    $bank = PaymentAccount::create(['name' => 'City Bank', 'type' => 'bank', 'account_number' => '1234567890', 'is_active' => true]);

    Livewire::test(ExpenseForm::class)
        ->set('category', 'Rent')
        ->set('amount', '15000')
        ->set('method', 'bank_transfer')
        ->set('payment_account_id', $bank->id)
        ->set('paid_to', 'Landlord')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('expenses.index'));

    $expense = Expense::sole();
    expect($expense->type)->toBe('expense')
        ->and($expense->category)->toBe('Rent')
        ->and((float) $expense->amount)->toBe(15000.0)
        ->and($expense->payment_account_id)->toBe($bank->id)
        ->and($expense->created_by)->toBe($this->user->id)
        ->and($expense->shift_id)->toBeNull()
        ->and($expense->expense_number)->toStartWith('EXP')
        ->and($expense->expense_date->isToday())->toBeTrue();
});

it('links a cash expense to the open till when paid from the drawer', function () {
    $shift = app(ShiftService::class)->open($this->user, $this->warehouse->id, 1000);

    Livewire::test(ExpenseForm::class)
        ->set('category', 'Cleaning')
        ->set('amount', '200')
        ->set('method', 'cash')
        ->set('fromTill', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(Expense::sole()->shift_id)->toBe($shift->id);
});

it('does not link to a till when paying by card', function () {
    app(ShiftService::class)->open($this->user, $this->warehouse->id, 1000);
    $card = PaymentAccount::create(['name' => 'Visa', 'type' => 'card', 'is_active' => true]);

    Livewire::test(ExpenseForm::class)
        ->set('category', 'Marketing')
        ->set('amount', '500')
        ->set('fromTill', true)
        ->set('method', 'card')
        ->set('payment_account_id', $card->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Expense::sole()->shift_id)->toBeNull();
});

it('updates an existing expense', function () {
    $expense = Expense::create([
        'category' => 'Water', 'amount' => 100, 'expense_date' => today(),
        'method' => 'cash', 'created_by' => $this->user->id,
    ]);

    Livewire::test(ExpenseForm::class, ['expense' => $expense])
        ->assertSet('category', 'Water')
        ->set('amount', '180')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $expense->fresh()->amount)->toBe(180.0)
        ->and(Expense::count())->toBe(1);
});

it('lists expenses but hides till cash moves unless asked', function () {
    $rent = Expense::create(['category' => 'Rent', 'amount' => 5000, 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);
    $drop = Expense::create(['type' => 'cash_out', 'category' => 'Drawer cash out', 'amount' => 3000, 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);

    Livewire::test(ExpenseList::class)
        ->assertSee($rent->expense_number)
        ->assertDontSee($drop->expense_number)
        ->assertViewHas('summary', fn ($s) => $s['total'] == 5000 && $s['count'] === 1 && $s['top_category'] === 'Rent')
        ->set('showDrawerMoves', true)
        ->assertSee($drop->expense_number)
        // Tiles still only count real costs.
        ->assertViewHas('summary', fn ($s) => $s['total'] == 5000);
});

it('filters the list by category and search', function () {
    $rent = Expense::create(['category' => 'Rent', 'amount' => 5000, 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);
    $power = Expense::create(['category' => 'Electricity', 'amount' => 900, 'paid_to' => 'DESCO', 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);

    Livewire::test(ExpenseList::class)
        ->set('categoryFilter', 'Rent')
        ->assertSee($rent->expense_number)
        ->assertDontSee($power->expense_number)
        ->set('categoryFilter', '')
        ->set('search', 'DESCO')
        ->assertSee($power->expense_number)
        ->assertDontSee($rent->expense_number);
});

it('deletes an expense', function () {
    $expense = Expense::create(['category' => 'Rent', 'amount' => 5000, 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);

    Livewire::test(ExpenseList::class)
        ->call('confirmDelete', $expense->id)
        ->call('delete');

    expect(Expense::count())->toBe(0)
        ->and(Expense::withTrashed()->count())->toBe(1);
});

it('renders the expense pages for admins', function () {
    $expense = Expense::create(['category' => 'Rent', 'amount' => 5000, 'expense_date' => today(), 'method' => 'cash', 'created_by' => $this->user->id]);

    $this->get(route('expenses.index'))->assertOk()->assertSee($expense->expense_number);
    $this->get(route('expenses.create'))->assertOk();
    $this->get(route('expenses.edit', $expense))->assertOk();
});

it('forbids staff from the expense pages', function () {
    $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);

    $this->actingAs($staff)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('expenses.create'))->assertForbidden();
});

<?php

namespace App\Livewire\Reports;

use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Money in (sale payments) and out (supplier payments) per bank account,
 * card or wallet, over a date range.
 */
class PaymentAccountReport extends Component
{
    use WithPagination;

    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $accountId = '';

    public function mount(): void
    {
        $this->setDates();
    }

    public function updatedPeriod(): void
    {
        $this->setDates();
        $this->resetPage();
    }

    public function updating($name): void
    {
        if (in_array($name, ['dateFrom', 'dateTo', 'accountId'], true)) {
            $this->resetPage();
        }
    }

    private function setDates(): void
    {
        match ($this->period) {
            'today' => [$this->dateFrom, $this->dateTo] = [today()->format('Y-m-d'), today()->format('Y-m-d')],
            'yesterday' => [$this->dateFrom, $this->dateTo] = [today()->subDay()->format('Y-m-d'), today()->subDay()->format('Y-m-d')],
            'this_week' => [$this->dateFrom, $this->dateTo] = [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'this_month' => [$this->dateFrom, $this->dateTo] = [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            'last_month' => [$this->dateFrom, $this->dateTo] = [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
            'this_year' => [$this->dateFrom, $this->dateTo] = [now()->startOfYear()->format('Y-m-d'), now()->endOfYear()->format('Y-m-d')],
            default => null,
        };
    }

    /**
     * Every account-tagged money movement in the range, one row each:
     *   in  — sale payments, and refunds a supplier paid back on a return
     *   out — supplier payments, refunds to customers, and expenses
     * Cash (no account) is excluded: it is counted per shift instead.
     */
    private function movements()
    {
        $range = fn ($q, string $col, string $accountCol) => $q
            ->whereNotNull($accountCol)
            ->when($this->dateFrom, fn ($q) => $q->whereDate($col, '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate($col, '<=', $this->dateTo))
            ->when($this->accountId, fn ($q) => $q->where($accountCol, $this->accountId));

        $salePayments = fn (string $status, string $direction) => $range(DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.status', $status), 'payments.payment_date', 'payments.payment_account_id')
            ->selectRaw('? as direction, ? as doc_type, payments.id, payments.payment_number, payments.payment_date,
                payments.payment_account_id, payments.method, payments.amount, payments.reference,
                invoices.id as doc_id, invoices.invoice_number as doc_number, invoices.customer_name as party', [$direction, 'invoice']);

        $supplierPayments = fn (string $type, string $direction) => $range(DB::table('purchase_payments')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_payments.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.type', $type), 'purchase_payments.payment_date', 'purchase_payments.payment_account_id')
            ->selectRaw('? as direction, ? as doc_type, purchase_payments.id, purchase_payments.payment_number, purchase_payments.payment_date,
                purchase_payments.payment_account_id, purchase_payments.method, purchase_payments.amount, purchase_payments.reference,
                purchase_orders.id as doc_id, purchase_orders.order_number as doc_number, suppliers.name as party', [$direction, $type === 'return' ? 'purchase_return' : 'purchase']);

        $expenses = $range(DB::table('expenses')->whereNull('expenses.deleted_at')->where('expenses.type', 'expense'),
            'expenses.expense_date', 'expenses.payment_account_id')
            ->selectRaw('? as direction, ? as doc_type, expenses.id, expenses.expense_number as payment_number, expenses.expense_date as payment_date,
                expenses.payment_account_id, expenses.method, expenses.amount, expenses.reference,
                expenses.id as doc_id, expenses.expense_number as doc_number, COALESCE(expenses.paid_to, expenses.category) as party', ['out', 'expense']);

        return $salePayments('completed', 'in')
            ->unionAll($supplierPayments('return', 'in'))
            ->unionAll($supplierPayments('purchase', 'out'))
            ->unionAll($salePayments('refunded', 'out'))
            ->unionAll($expenses);
    }

    public function render()
    {
        $totals = DB::query()->fromSub($this->movements(), 'm')
            ->selectRaw('payment_account_id as account_id, direction, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_account_id', 'direction')
            ->get();

        $in = $totals->where('direction', 'in')->keyBy('account_id');
        $out = $totals->where('direction', 'out')->keyBy('account_id');

        // Deleted accounts still appear when they have activity in the range.
        $accounts = PaymentAccount::withTrashed()
            ->where(fn ($q) => $q
                ->whereNull('deleted_at')
                ->orWhereIn('id', $totals->pluck('account_id')))
            ->when($this->accountId, fn ($q) => $q->whereKey($this->accountId))
            ->orderBy('name')
            ->get()
            ->map(fn ($account) => (object) [
                'account' => $account,
                'in' => (float) ($in[$account->id]->total ?? 0),
                'out' => (float) ($out[$account->id]->total ?? 0),
                'count' => (int) ($in[$account->id]->count ?? 0) + (int) ($out[$account->id]->count ?? 0),
            ]);

        $summary = [
            'in' => $accounts->sum('in'),
            'out' => $accounts->sum('out'),
        ];
        $summary['net'] = $summary['in'] - $summary['out'];

        $transactions = DB::query()
            ->fromSub($this->movements(), 'movements')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(25);
        $accountNames = PaymentAccount::withTrashed()->get()->mapWithKeys(fn ($a) => [$a->id => $a->display_name]);
        $accountOptions = PaymentAccount::withTrashed()->orderBy('name')->get();

        return view('livewire.reports.payment-account-report', compact('accounts', 'summary', 'transactions', 'accountNames', 'accountOptions'))
            ->layout('layouts.app', ['title' => 'Account Report']);
    }
}

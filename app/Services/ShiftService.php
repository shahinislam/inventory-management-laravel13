<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Till sessions. A cashier opens a shift with a float, sells, and closes it by
 * counting the drawer. The expected cash is everything cash that went through
 * the drawer during the shift, so any shortage or excess shows up at close.
 */
class ShiftService
{
    public function currentFor(?User $user): ?CashShift
    {
        return $user ? CashShift::open()->where('user_id', $user->id)->latest('opened_at')->first() : null;
    }

    public function open(User $user, ?int $warehouseId, float $openingCash, ?string $notes = null): CashShift
    {
        if ($this->currentFor($user)) {
            throw ValidationException::withMessages(['openingCash' => 'You already have an open shift.']);
        }

        return CashShift::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouseId,
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash' => max(0, $openingCash),
            'notes' => $notes,
        ]);
    }

    /** Cash in or out of the drawer (float top-up, bank drop, petty cash). */
    public function moveCash(CashShift $shift, string $direction, float $amount, string $reason): Expense
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['cashAmount' => 'This shift is closed.']);
        }

        return Expense::create([
            'type' => $direction === 'in' ? 'cash_in' : 'cash_out',
            'category' => $direction === 'in' ? 'Drawer top-up' : 'Drawer cash out',
            'amount' => $amount,
            'expense_date' => today(),
            'method' => 'cash',
            'shift_id' => $shift->id,
            'notes' => $reason,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Why an expense can no longer be changed, or null when it can. Cash paid
     * from a till is part of that shift's count, so once the shift is closed
     * editing or deleting the expense would quietly change a settled drawer.
     */
    public function expenseLockReason(Expense $expense): ?string
    {
        $shift = $expense->shift_id ? $expense->shift : null;

        if (! $shift || $shift->isOpen()) {
            return null;
        }

        return "This expense was paid from the till in shift {$shift->shift_number}, which is closed. "
            ."It is part of that shift's cash count, so it can no longer be edited or deleted.";
    }

    /**
     * Everything that moved through the drawer, by line, for the close screen
     * and the Z-report.
     */
    public function summary(CashShift $shift): array
    {
        $payments = Payment::where('shift_id', $shift->id);

        $byMethod = (clone $payments)->where('status', 'completed')
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('method')->get()->keyBy('method');

        $refundsByMethod = (clone $payments)->where('status', 'refunded')
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')->pluck('total', 'method');

        $drawer = Expense::where('shift_id', $shift->id)->where('method', 'cash')
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

        $cashSales = (float) ($byMethod['cash']->total ?? 0);
        $cashRefunds = (float) ($refundsByMethod['cash'] ?? 0);
        $cashIn = (float) ($drawer['cash_in'] ?? 0);
        $cashOut = (float) ($drawer['cash_out'] ?? 0);
        $cashExpenses = (float) ($drawer['expense'] ?? 0);

        // Cash handed to a supplier leaves the drawer; a supplier's cash refund
        // (paid against a purchase return) comes back into it.
        $supplierCash = PurchasePayment::where('purchase_payments.shift_id', $shift->id)
            ->where('purchase_payments.method', 'cash')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_payments.purchase_order_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN purchase_orders.type = 'return' THEN 0 ELSE purchase_payments.amount END), 0) as paid,
                COALESCE(SUM(CASE WHEN purchase_orders.type = 'return' THEN purchase_payments.amount ELSE 0 END), 0) as refunded")
            ->toBase()->first();
        $supplierPaid = (float) $supplierCash->paid;
        $supplierRefunds = (float) $supplierCash->refunded;

        $sales = $shift->invoices()->sales();
        $returns = $shift->invoices()->returns();

        $drawerTotal = round((float) $shift->opening_cash + $cashSales - $cashRefunds + $cashIn - $cashOut - $cashExpenses
            - $supplierPaid + $supplierRefunds, 2);
        // A closed shift was counted against the figure stored at close; that
        // figure stands even if something linked to the shift changes later.
        $closed = ! $shift->isOpen() && $shift->expected_cash !== null;

        return [
            'closed' => $closed,
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_expenses' => $cashExpenses,
            'supplier_paid' => $supplierPaid,
            'supplier_refunds' => $supplierRefunds,
            'expected_cash' => $closed ? (float) $shift->expected_cash : $drawerTotal,
            // What the lines above add up to now; differs from expected_cash
            // only when a closed shift's records were changed after the count.
            'drawer_total' => $drawerTotal,
            'by_method' => collect(Payment::methods())->map(fn ($label, $method) => [
                'label' => $label,
                'total' => (float) ($byMethod[$method]->total ?? 0),
                'count' => (int) ($byMethod[$method]->count ?? 0),
                'refunded' => (float) ($refundsByMethod[$method] ?? 0),
            ])->filter(fn ($row) => $row['total'] > 0 || $row['refunded'] > 0)->all(),
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('total'),
            'due_given' => $closed ? $this->dueAtClose($shift) : (float) (clone $sales)->sum('due_amount'),
            'returns_count' => (clone $returns)->count(),
            'returns_total' => (float) (clone $returns)->sum('total'),
        ];
    }

    /**
     * What the shift's sales still owed when the shift closed. due_amount moves
     * on as customers pay later or bring goods back, so it is rebuilt from what
     * had happened by the close: the bill, less payments taken and less the
     * part of each return that was taken off the due rather than refunded.
     */
    private function dueAtClose(CashShift $shift): float
    {
        $sales = $shift->invoices()->sales()->get(['id', 'total']);

        if ($sales->isEmpty()) {
            return 0.0;
        }

        $paid = Payment::whereIn('invoice_id', $sales->modelKeys())
            ->where('status', 'completed')
            ->where('created_at', '<=', $shift->closed_at)
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        // A return note's paid_amount is the refund; the rest came off the due.
        $credited = Invoice::query()->returns()
            ->whereIn('parent_invoice_id', $sales->modelKeys())
            ->where('created_at', '<=', $shift->closed_at)
            ->selectRaw('parent_invoice_id, SUM(total - paid_amount) as total')
            ->groupBy('parent_invoice_id')
            ->pluck('total', 'parent_invoice_id');

        return round($sales->sum(fn (Invoice $sale) => max(0, (float) $sale->total
            - (float) ($paid[$sale->id] ?? 0) - (float) ($credited[$sale->id] ?? 0))), 2);
    }

    public function close(CashShift $shift, float $countedCash, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $notes) {
            $shift = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages(['countedCash' => 'This shift is already closed.']);
            }

            $expected = $this->summary($shift)['expected_cash'];

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'difference' => round($countedCash - $expected, 2),
                'notes' => trim(($shift->notes ? $shift->notes."\n" : '').($notes ?? '')) ?: null,
            ]);

            return $shift;
        });
    }
}

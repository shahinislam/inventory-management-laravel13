<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Payment;
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

        $sales = $shift->invoices()->sales();
        $returns = $shift->invoices()->returns();

        return [
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_expenses' => $cashExpenses,
            'expected_cash' => round((float) $shift->opening_cash + $cashSales - $cashRefunds + $cashIn - $cashOut - $cashExpenses, 2),
            'by_method' => collect(Payment::METHODS)->map(fn ($label, $method) => [
                'label' => $label,
                'total' => (float) ($byMethod[$method]->total ?? 0),
                'count' => (int) ($byMethod[$method]->count ?? 0),
                'refunded' => (float) ($refundsByMethod[$method] ?? 0),
            ])->filter(fn ($row) => $row['total'] > 0 || $row['refunded'] > 0)->all(),
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('total'),
            'due_given' => (float) (clone $sales)->sum('due_amount'),
            'returns_count' => (clone $returns)->count(),
            'returns_total' => (float) (clone $returns)->sum('total'),
        ];
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

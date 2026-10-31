<?php

namespace App\Services;

use Illuminate\Support\Facades\Date;

/**
 * Profit figures for the partnership module.
 *
 * The partners share the net profit of the Profit & Loss for the same period,
 * so both screens always show the same number: net sales (excluding tax) less
 * the cost of the goods sold, stock written off and expenses, plus the courier
 * margin. Stock still on the shelf is not a cost until it is sold.
 */
class PartnershipService
{
    public function __construct(private ProfitLossService $profitLoss) {}

    /**
     * @return array{
     *     total_sales: float,
     *     total_costs: float,
     *     cogs: float,
     *     stock_loss: float,
     *     expenses: float,
     *     courier_margin: float,
     *     profit: float,
     *     invoice_count: int
     * }
     */
    public function summary(string $from, string $to): array
    {
        $pl = $this->profitLoss->statement(Date::parse($from)->startOfDay(), Date::parse($to)->endOfDay());

        return [
            'total_sales' => $pl['net_sales'],
            // Everything the statement takes off net sales, worked back from the
            // rounded profit so that sales − costs + courier = profit exactly.
            'total_costs' => round($pl['net_sales'] + $pl['courier_margin'] - $pl['net_profit'], 2),
            'cogs' => $pl['cogs'],
            'stock_loss' => $pl['stock_loss'],
            'expenses' => $pl['expense_total'],
            'courier_margin' => $pl['courier_margin'],
            'profit' => $pl['net_profit'],
            'invoice_count' => $pl['invoices'],
        ];
    }
}

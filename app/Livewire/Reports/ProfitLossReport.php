<?php

namespace App\Livewire\Reports;

use App\Concerns\ResolvesReportPeriod;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Profit & loss for a period, written for shop owners rather than accountants.
 *
 *   Net sales (excl. VAT) = goods subtotal − bill discounts − member discounts − returns
 *   Gross profit          = net sales − cost of goods sold
 *   Net profit            = gross profit + courier margin − expenses
 *
 * VAT collected is shown separately: it belongs to the government, not the shop.
 */
class ProfitLossReport extends Component
{
    use ResolvesReportPeriod;

    /** Sale statuses that never count as revenue. */
    public const EXCLUDED_STATUSES = ['draft', 'cancelled'];

    private function sales(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Invoice::query()->sales()
            ->whereNotIn('invoices.status', self::EXCLUDED_STATUSES)
            ->whereBetween('invoices.invoice_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function returns(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Invoice::query()->returns()
            ->whereNotIn('invoices.status', self::EXCLUDED_STATUSES)
            ->whereBetween('invoices.invoice_date', [$from->toDateString(), $to->toDateString()]);
    }

    /** Invoice lines belonging to the given invoice query. */
    private function lines(Builder $invoices): Builder
    {
        return InvoiceItem::query()
            ->whereIn('invoice_items.invoice_id', $invoices->select('invoices.id'));
    }

    /**
     * Every figure of the statement for one period.
     *
     * @return array<string, mixed>
     */
    public function statement(CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = $this->sales($from, $to)->selectRaw('
            COUNT(*) as invoices,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(tax), 0) as tax,
            COALESCE(SUM(discount), 0) as discount,
            COALESCE(SUM(membership_discount), 0) as membership_discount,
            COALESCE(SUM(courier_charge), 0) as courier_charge,
            COALESCE(SUM(courier_cost), 0) as courier_cost
        ')->toBase()->first();

        $returns = (float) $this->returns($from, $to)->sum('invoices.total');

        $soldCost = (float) $this->lines($this->sales($from, $to))
            ->selectRaw('COALESCE(SUM(invoice_items.quantity * invoice_items.unit_cost), 0) as v')
            ->toBase()->value('v');

        // Restocked returns go back on the shelf, so their cost comes off COGS.
        // Damaged ones (restock = false) stay in COGS: the shop lost those goods.
        $restockedCost = (float) $this->lines($this->returns($from, $to))
            ->where('invoice_items.restock', true)
            ->selectRaw('COALESCE(SUM(invoice_items.quantity * invoice_items.unit_cost), 0) as v')
            ->toBase()->value('v');

        $unknownCost = $this->lines($this->sales($from, $to))
            ->where('invoice_items.unit_cost', '<=', 0)
            ->where('invoice_items.is_gift', false)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(invoice_items.quantity * invoice_items.unit_price), 0) as v')
            ->toBase()->first();

        $expenses = Expense::query()->costs()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->toBase()
            ->get()
            ->groupBy(fn ($r) => filled($r->category) ? $r->category : 'Uncategorised')
            ->map(fn ($rows, $label) => ['category' => $label, 'total' => round((float) $rows->sum('total'), 2)])
            ->sortByDesc('total')
            ->values()
            ->all();

        $subtotal = (float) $totals->subtotal;
        $tax = (float) $totals->tax;
        $discounts = (float) $totals->discount + (float) $totals->membership_discount;
        $netSales = $subtotal - $discounts - $returns;
        $cogs = $soldCost - $restockedCost;
        $grossProfit = $netSales - $cogs;
        $courierMargin = (float) $totals->courier_charge - (float) $totals->courier_cost;
        $expenseTotal = array_sum(array_column($expenses, 'total'));
        $netProfit = $grossProfit + $courierMargin - $expenseTotal;

        return [
            'invoices' => (int) $totals->invoices,
            'gross_sales' => round($subtotal + $tax, 2),
            'goods_sales' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round((float) $totals->discount, 2),
            'membership_discount' => round((float) $totals->membership_discount, 2),
            'discounts' => round($discounts, 2),
            'returns' => round($returns, 2),
            'net_sales' => round($netSales, 2),
            'sold_cost' => round($soldCost, 2),
            'restocked_cost' => round($restockedCost, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin' => $netSales > 0 ? round($grossProfit / $netSales * 100, 1) : null,
            'courier_charge' => round((float) $totals->courier_charge, 2),
            'courier_cost' => round((float) $totals->courier_cost, 2),
            'courier_margin' => round($courierMargin, 2),
            'expenses' => $expenses,
            'expense_total' => round($expenseTotal, 2),
            'net_profit' => round($netProfit, 2),
            'net_margin' => $netSales > 0 ? round($netProfit / $netSales * 100, 1) : null,
            'unknown_cost_lines' => (int) $unknownCost->n,
            'unknown_cost_sales' => round((float) $unknownCost->v, 2),
        ];
    }

    /**
     * Gross profit per product category: line sales (after line discounts,
     * before bill discounts and VAT) less cost, net of returns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categoryBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $lineNet = 'invoice_items.quantity * invoice_items.unit_price * (1 - invoice_items.discount / 100)';
        $lineCost = 'invoice_items.quantity * invoice_items.unit_cost';

        $byCategory = fn (Builder $lines, string $aggregates) => $lines
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw("categories.id as category_id, categories.name as category, {$aggregates}")
            ->groupBy('categories.id', 'categories.name')
            ->toBase()->get()
            ->keyBy(fn ($r) => (int) $r->category_id);

        $sold = $byCategory(
            $this->lines($this->sales($from, $to)),
            "SUM({$lineNet}) as sales, SUM({$lineCost}) as cost, SUM(invoice_items.quantity) as qty",
        );

        // Return lines carry the refunded value; only restocked units give their cost back.
        $returned = $byCategory(
            $this->lines($this->returns($from, $to)),
            "SUM(invoice_items.subtotal) as sales, SUM(CASE WHEN invoice_items.restock = 1 THEN {$lineCost} ELSE 0 END) as cost, SUM(invoice_items.quantity) as qty",
        );

        return $sold->keys()->merge($returned->keys())->unique()
            ->map(function ($id) use ($sold, $returned) {
                $sales = (float) ($sold[$id]->sales ?? 0) - (float) ($returned[$id]->sales ?? 0);
                $cost = (float) ($sold[$id]->cost ?? 0) - (float) ($returned[$id]->cost ?? 0);
                $profit = $sales - $cost;

                return [
                    'category' => ($sold[$id] ?? $returned[$id])->category ?? 'Uncategorised',
                    'qty' => (float) ($sold[$id]->qty ?? 0) - (float) ($returned[$id]->qty ?? 0),
                    'sales' => round($sales, 2),
                    'cost' => round($cost, 2),
                    'profit' => round($profit, 2),
                    'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : null,
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    /** Percentage change from $previous to $current, or null when there is no base. */
    public static function change(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    public function render()
    {
        [$from, $to] = $this->periodRange();
        [$prevFrom, $prevTo] = $this->previousPeriodRange();

        return view('livewire.reports.profit-loss-report', [
            'current' => $this->statement($from, $to),
            'previous' => $this->statement($prevFrom, $prevTo),
            'categories' => $this->categoryBreakdown($from, $to),
            'rangeLabel' => $this->periodLabel(),
            'previousLabel' => $prevFrom->isSameDay($prevTo)
                ? $prevFrom->format('d M Y')
                : $prevFrom->format('d M Y').' – '.$prevTo->format('d M Y'),
        ])->layout('layouts.app', ['title' => 'Profit & Loss']);
    }
}

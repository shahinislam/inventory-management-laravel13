<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Profit & loss for a period, written for shop owners rather than accountants.
 *
 *   Net sales (excl. tax) = goods subtotal − bill discounts − member discounts − returns
 *   Gross profit          = net sales − cost of goods sold
 *   Net profit            = gross profit + courier margin − stock written off − expenses
 *
 * Tax collected is shown separately: it belongs to the government, not the shop.
 * The Profit & Loss report and the partnership profit both read from here, so
 * the two always agree.
 */
class ProfitLossService
{
    /** Sale statuses that never count as revenue. */
    public const EXCLUDED_STATUSES = ['draft', 'cancelled'];

    /** Stock movements that change stock without a sale, purchase, return or transfer. */
    public const WRITE_OFF_TYPES = ['damaged', 'expired', 'adjustment'];

    /**
     * Adjustments with these notes put stock into the system for the first time
     * (ProductForm's opening stock, the "Initial stock entry" reason on Stock
     * Adjustment). That stock was never lost or found, so it is left out.
     */
    private const OPENING_STOCK_NOTES = ['Opening stock%', 'Initial stock entry%'];

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
     * Stock taken out without a sale (damaged, expired, short at a count), at
     * cost, and stock found again by a count or adjustment.
     *
     * Finds are netted off the losses: a unit written off as missing and found
     * later was never lost, and if the find were ignored that unit's cost would
     * be charged twice, once here and again in cost of goods sold when it sells.
     */
    private function stockWrittenOff(CarbonInterface $from, CarbonInterface $to): object
    {
        $movements = StockMovement::query()
            ->whereIn('type', self::WRITE_OFF_TYPES)
            ->whereBetween('created_at', [$from, $to]);

        foreach (self::OPENING_STOCK_NOTES as $pattern) {
            $movements->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', 'not like', $pattern));
        }

        return $movements->selectRaw('
            COALESCE(SUM(CASE WHEN after_quantity < before_quantity THEN quantity * unit_cost ELSE 0 END), 0) as lost,
            COALESCE(SUM(CASE WHEN after_quantity > before_quantity THEN quantity * unit_cost ELSE 0 END), 0) as found
        ')->toBase()->first();
    }

    /**
     * Every figure of the statement for one period.
     *
     * @return array<string, mixed>
     */
    public function statement(CarbonInterface $from, CarbonInterface $to): array
    {
        // With prices that include tax, part of a bill discount is tax given up, not
        // sales (PricingService::discountTax()). It is the gap between the bill less
        // courier and tax, and the net goods less the discounts as given.
        $totals = $this->sales($from, $to)->selectRaw('
            COUNT(*) as invoices,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(tax), 0) as tax,
            COALESCE(SUM(discount), 0) as discount,
            COALESCE(SUM(membership_discount), 0) as membership_discount,
            COALESCE(SUM(CASE WHEN prices_include_tax = 1
                THEN (total - courier_charge - tax) - (subtotal - discount - membership_discount)
                ELSE 0 END), 0) as discount_tax,
            COALESCE(SUM(total - courier_charge), 0) as billed,
            COALESCE(SUM(courier_charge), 0) as courier_charge,
            COALESCE(SUM(courier_cost), 0) as courier_cost
        ')->toBase()->first();

        // A refund gives back goods value and the tax on it; each comes off its own line.
        $returned = $this->returns($from, $to)->selectRaw('
            COALESCE(SUM(total - tax), 0) as goods,
            COALESCE(SUM(tax), 0) as tax
        ')->toBase()->first();
        $returns = (float) $returned->goods;

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
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(invoice_items.subtotal - invoice_items.tax_amount), 0) as v')
            ->toBase()->first();

        $stock = $this->stockWrittenOff($from, $to);

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
        $tax = (float) $totals->tax - (float) $returned->tax;
        $faceDiscounts = (float) $totals->discount + (float) $totals->membership_discount;
        $discountTax = (float) $totals->discount_tax;
        $discounts = $faceDiscounts - $discountTax;
        // Split the tax part over bill and member discounts by their size.
        $exTax = fn (float $v) => $faceDiscounts > 0 ? $v - $discountTax * $v / $faceDiscounts : $v;
        $netSales = $subtotal - $discounts - $returns;
        $cogs = $soldCost - $restockedCost;
        $grossProfit = $netSales - $cogs;
        $courierMargin = (float) $totals->courier_charge - (float) $totals->courier_cost;
        $stockLoss = (float) $stock->lost - (float) $stock->found;
        $expenseTotal = array_sum(array_column($expenses, 'total'));
        $netProfit = $grossProfit + $courierMargin - $stockLoss - $expenseTotal;

        return [
            'invoices' => (int) $totals->invoices,
            // What the bills asked for the goods: tax in, discounts off, courier out.
            'gross_sales' => round((float) $totals->billed, 2),
            'returns_incl_tax' => round($returns + (float) $returned->tax, 2),
            'goods_sales' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'discount' => round($exTax((float) $totals->discount), 2),
            'membership_discount' => round($exTax((float) $totals->membership_discount), 2),
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
            'stock_lost' => round((float) $stock->lost, 2),
            'stock_found' => round((float) $stock->found, 2),
            'stock_loss' => round($stockLoss, 2),
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
     * before bill discounts and tax) less cost, net of returns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categoryBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        // What the line was charged less the tax inside it, whether prices included tax or not.
        $lineNet = 'invoice_items.subtotal - invoice_items.tax_amount';
        $lineCost = 'invoice_items.quantity * invoice_items.unit_cost';

        // A return line is refunded after the bill discount, but sales here are
        // counted before it. Value the return at the sold line's own price, for
        // the share of it that came back, so a fully returned sale nets to zero.
        $returnedNet = 'CASE WHEN sold.quantity > 0
            THEN (sold.subtotal - sold.tax_amount) * invoice_items.quantity / sold.quantity
            ELSE invoice_items.subtotal - invoice_items.tax_amount END';

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

        // Only restocked units give their cost back.
        $returned = $byCategory(
            $this->lines($this->returns($from, $to))
                ->leftJoin('invoice_items as sold', 'invoice_items.parent_item_id', '=', 'sold.id'),
            "SUM({$returnedNet}) as sales, SUM(CASE WHEN invoice_items.restock = 1 THEN {$lineCost} ELSE 0 END) as cost, SUM(invoice_items.quantity) as qty",
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
                    'margin' => round($sales, 2) > 0 ? round($profit / $sales * 100, 1) : null,
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }
}

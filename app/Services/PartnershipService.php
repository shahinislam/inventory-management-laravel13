<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Profit figures for the partnership module.
 *
 * Deliberately cash basis: profit is money in from sales less money out on
 * purchases over the same window. It is not accrual accounting — a large stock
 * purchase reads as a loss in the month it is bought, even though the stock is
 * still on the shelf. The report says so on screen.
 */
class PartnershipService
{
    /** Invoice statuses that represent money actually earned. */
    public const SALE_STATUSES = ['paid', 'partial', 'returned'];

    /** Purchase statuses that represent money actually committed. */
    public const PURCHASE_STATUSES = ['ordered', 'received'];

    /**
     * @return array{
     *     total_sales: float,
     *     total_purchases: float,
     *     courier_margin: float,
     *     profit: float,
     *     invoice_count: int,
     *     purchase_count: int
     * }
     */
    public function summary(string $from, string $to): array
    {
        $invoices = Invoice::sales()
            ->whereIn('status', self::SALE_STATUSES)
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to);

        // Sales exclude the courier charge, which is added back separately as a
        // margin so that pass-through delivery nets to zero and absorbed
        // delivery shows as the real cost it is.
        $sales = (float) (clone $invoices)->sum(
            DB::raw('total - courier_charge - returned_amount')
        );

        $courierMargin = (float) (clone $invoices)->sum(
            DB::raw('courier_charge - courier_cost')
        );

        $purchases = PurchaseOrder::purchases()
            ->whereIn('status', self::PURCHASE_STATUSES)
            ->whereDate('order_date', '>=', $from)
            ->whereDate('order_date', '<=', $to);

        // Supplier freight sits inside purchase_orders.total already: it is money
        // genuinely spent, so it is not netted out the way sales courier is.
        $purchaseTotal = (float) (clone $purchases)->sum(DB::raw('total - returned_amount'));

        return [
            'total_sales' => round($sales, 2),
            'total_purchases' => round($purchaseTotal, 2),
            'courier_margin' => round($courierMargin, 2),
            'profit' => round($sales - $purchaseTotal + $courierMargin, 2),
            'invoice_count' => (clone $invoices)->count(),
            'purchase_count' => (clone $purchases)->count(),
        ];
    }
}

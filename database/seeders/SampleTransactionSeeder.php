<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PricingService;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demo history so every screen has something to show: a month of everyday
 * till sales (taxed like the POS taxes them), paid, part-paid and fully-due
 * records for the Due and Account reports, a few expenses and one running
 * promotion.
 *
 * Runs once — skipped if any invoice or purchase order already exists. A
 * fixed random seed gives every install the same data.
 */
class SampleTransactionSeeder extends Seeder
{
    private User $user;

    private Warehouse $warehouse;

    private bool $inclusive;

    public function run(): void
    {
        if (Invoice::exists() || PurchaseOrder::exists()) {
            return;
        }

        $this->user = User::where('email', 'admin@inventory.com')->first() ?? User::first();
        $this->warehouse = Warehouse::getDefault() ?? Warehouse::first();
        $this->inclusive = PricingService::pricesIncludeTax();

        $account = fn (string $name) => PaymentAccount::where('name', $name)->value('id');
        $products = Product::whereHas('warehouses', fn ($q) => $q->where('product_warehouse.quantity', '>', 20))
            ->orderBy('id')->take(4)->get()->values();
        $customers = Customer::orderBy('id')->get()->values();
        $suppliers = Supplier::orderBy('id')->get()->values();

        if (! $this->user || ! $this->warehouse || $products->count() < 4 || $customers->isEmpty() || $suppliers->isEmpty()) {
            return;
        }

        $promoted = Category::where('slug', 'snacks')->value('id');

        // ============ SALES ============
        // Planned first, then created oldest-first so invoice numbers follow the dates.

        $sales = [
            // Fully paid by card.
            ['day' => 12, 'customer' => $customers[0], 'lines' => [[$products[0], 2], [$products[1], 3]],
                'payments' => [['card', $account('Company Visa'), null]]],

            // Fully paid by bank transfer.
            ['day' => 8, 'customer' => $customers[1 % $customers->count()], 'lines' => [[$products[2], 5]],
                'payments' => [['bank_transfer', $account('Main Checking'), null]]],

            // Part paid by mobile wallet, rest overdue.
            ['day' => 20, 'customer' => $customers[2 % $customers->count()], 'lines' => [[$products[0], 4], [$products[3], 2]],
                'payments' => [['mobile_banking', $account('Wallet Merchant'), 0.4]], 'due_in' => -5],

            // Nothing paid yet, due next week.
            ['day' => 3, 'customer' => $customers[0], 'lines' => [[$products[1], 6]],
                'payments' => [], 'due_in' => 7],
        ];

        $stock = DB::table('product_warehouse')->where('warehouse_id', $this->warehouse->id)
            ->pluck('quantity', 'product_id')->map(fn ($q) => (float) $q)->all();

        foreach ($sales as $sale) {
            foreach ($sale['lines'] as [$product, $qty]) {
                $stock[$product->id] -= $qty;
            }
        }

        $sales = [...$sales, ...$this->everydaySales($customers, $account, $stock, $promoted)];

        // Stable sort: same-day sales keep their planned order.
        usort($sales, fn ($a, $b) => $b['day'] <=> $a['day']);

        $perDay = [];
        foreach ($sales as $sale) {
            $sale['at'] = $this->saleTime($sale['day'], $perDay[$sale['day']] = ($perDay[$sale['day']] ?? 0) + 1);
            $this->sale($sale);
        }

        // ============ PURCHASES ============
        // Left in "ordered" (not received) so seeding does not change stock.

        // Fully paid by bank.
        $this->purchase($suppliers[0], [[$products[0], 20], [$products[2], 30]], daysAgo: 15,
            payments: [['bank_transfer', $account('Business Savings'), null]], dueInDays: 15);

        // Part paid by card, rest overdue.
        $this->purchase($suppliers[1 % $suppliers->count()], [[$products[1], 40]], daysAgo: 25,
            payments: [['card', $account('Amex Corporate'), 0.5]], dueInDays: -10);

        // Nothing paid, due later this month.
        $this->purchase($suppliers[2 % $suppliers->count()], [[$products[3], 25]], daysAgo: 4,
            payments: [], dueInDays: 20);

        // ============ EXPENSES ============

        foreach ([
            [26, 'Electricity', 86.40, 'bank_transfer', 'Main Checking', 'City Power & Light'],
            [19, 'Internet & Phone', 49.99, 'card', 'Company Visa', 'Springfield Telecom'],
            [12, 'Packaging', 62.50, 'cash', null, 'Harbor Packaging Supply'],
            [7, 'Cleaning', 28.00, 'cash', null, null],
            [2, 'Transport', 35.00, 'cash', null, 'Quick Courier'],
        ] as [$daysAgo, $category, $amount, $method, $accountName, $paidTo]) {
            Expense::create([
                'type' => 'expense',
                'category' => $category,
                'amount' => $amount,
                'expense_date' => today()->subDays($daysAgo),
                'method' => $method,
                'payment_account_id' => $accountName ? $account($accountName) : null,
                'paid_to' => $paidTo,
                'created_by' => $this->user->id,
            ]);
        }

        // ============ PROMOTION ============
        // Starts today; today's planned sales leave this category out, so no
        // seeded sale was priced without the discount it should have had.

        if ($promoted) {
            Promotion::create([
                'name' => 'Snack Week',
                'description' => '10% off every snack for a week.',
                'type' => 'percentage',
                'value' => 10,
                'category_id' => $promoted,
                'starts_at' => today(),
                'ends_at' => today()->addDays(7)->endOfDay(),
                'is_active' => true,
            ]);
        }
    }

    /**
     * A month of ordinary till sales, busier at weekends, so the dashboard
     * chart, Sales Report and Profit & Loss have a realistic shape. Each
     * product keeps a few units above its reorder level.
     *
     * @param  array<int, float>  $stock  on hand per product id, reduced as lines are planned
     * @return list<array<string, mixed>>
     */
    private function everydaySales(Collection $customers, Closure $account, array &$stock, ?int $promoted): array
    {
        mt_srand(2026);

        $shelf = Product::where('status', 'active')->orderBy('id')->get()
            ->filter(fn ($p) => ($stock[$p->id] ?? 0) > (float) $p->min_stock_level + 10)
            ->values();

        $methods = [
            ['cash', null], ['cash', null],
            ['card', $account('Company Visa')],
            ['mobile_banking', $account('Wallet Merchant')],
        ];

        $sales = [];

        for ($day = 29; $day >= 0; $day--) {
            $count = today()->subDays($day)->isWeekend() ? mt_rand(4, 6) : mt_rand(2, 4);

            for ($i = 0; $i < $count; $i++) {
                $lines = [];
                $picked = [];

                for ($n = mt_rand(1, 4); $n > 0; $n--) {
                    $product = $shelf[mt_rand(0, $shelf->count() - 1)];
                    $qty = $product->unit === 'kg' ? [0.5, 1, 1.5, 2][mt_rand(0, 3)] : mt_rand(1, 4);

                    if (isset($picked[$product->id])
                        || ($day === 0 && $product->category_id === $promoted)
                        || (float) $product->min_stock_level + 5 > $stock[$product->id] - $qty) {
                        continue;
                    }

                    $picked[$product->id] = true;
                    $stock[$product->id] -= $qty;
                    $lines[] = [$product, $qty];
                }

                if (! $lines) {
                    continue;
                }

                [$method, $accountId] = $methods[mt_rand(0, 3)];

                $sales[] = [
                    'day' => $day,
                    'customer' => mt_rand(1, 3) === 1 ? $customers[mt_rand(0, $customers->count() - 1)] : null,
                    'lines' => $lines,
                    'payments' => [[$method, $accountId, null]],
                ];
            }
        }

        return $sales;
    }

    /** Spread a day's sales through opening hours; today's stay in the past. */
    private function saleTime(int $daysAgo, int $nth): CarbonInterface
    {
        if ($daysAgo === 0) {
            return now()->subMinutes(40 * (8 - min($nth, 7)))->max(today());
        }

        return today()->subDays($daysAgo)->setTime(9, 0)->addMinutes(($nth * 97) % 600);
    }

    /**
     * $sale holds day, at, customer, lines ([product, qty] pairs), payments
     * ([method, account id, share of the total, null = all]) and optional due_in.
     *
     * @param  array<string, mixed>  $sale
     */
    private function sale(array $sale): void
    {
        $customer = $sale['customer'];
        $payments = $sale['payments'];
        $dueInDays = $sale['due_in'] ?? null;
        $at = $sale['at'];
        $date = $at->copy()->startOfDay();

        $amounts = collect($sale['lines'])->map(fn ($line) => PricingService::lineAmounts(
            (float) $line[0]->selling_price, 0, (float) $line[1], (float) $line[0]->tax_rate, $this->inclusive,
        ));

        $total = round($amounts->sum('gross'), 2);
        $paid = collect($payments)->sum(fn ($p) => round($total * ($p[2] ?? 1), 2));
        $due = max(0, round($total - $paid, 2));

        $invoice = Invoice::create([
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer?->id,
            'created_by' => $this->user->id,
            'customer_name' => $customer?->name ?? 'Walk-in Customer',
            'customer_phone' => $customer?->phone,
            'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'sent'),
            'payment_method' => $payments[0][0] ?? null,
            'prices_include_tax' => $this->inclusive,
            'subtotal' => round($amounts->sum('net'), 2),
            'tax' => round($amounts->sum('tax'), 2),
            'total' => $total,
            'paid_amount' => $paid,
            'due_amount' => $due,
            'invoice_date' => $date,
            'due_date' => $dueInDays !== null ? today()->addDays($dueInDays) : null,
            'paid_date' => $due <= 0 ? $date : null,
        ]);
        $invoice->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        foreach ($sale['lines'] as $i => [$product, $qty]) {
            $taken = app(InventoryService::class)->remove(
                productId: $product->id,
                warehouseId: $this->warehouse->id,
                quantity: $qty,
                type: 'sale',
                extra: ['reference' => $invoice],
            );

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $qty,
                'unit_price' => $product->selling_price,
                'unit_cost' => $taken['unit_cost'],
                'tax_rate' => $product->tax_rate,
                'subtotal' => $amounts[$i]['gross'],
                'tax_amount' => $amounts[$i]['tax'],
            ]);
        }

        // Date the stock movements with the sale, not with the moment of seeding.
        StockMovement::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)
            ->update(['created_at' => $at, 'updated_at' => $at]);

        foreach ($payments as [$method, $accountId, $share]) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'created_by' => $this->user->id,
                'payment_account_id' => $accountId,
                'amount' => round($total * ($share ?? 1), 2),
                'method' => $method,
                'reference' => $method === 'mobile_banking' ? 'TRX'.strtoupper(substr(md5((string) $invoice->id), 0, 8)) : null,
                'status' => 'completed',
                'payment_date' => $date,
            ]);
        }

        $customer?->increment('total_orders');
        $customer?->increment('total_purchases', $total);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     * @param  array<int, array{0: string, 1: ?int, 2: ?float}>  $payments
     */
    private function purchase(Supplier $supplier, array $lines, int $daysAgo, array $payments, int $dueInDays): void
    {
        $date = today()->subDays($daysAgo);
        $total = collect($lines)->sum(fn ($l) => (float) $l[0]->cost_price * $l[1]);
        $paid = collect($payments)->sum(fn ($p) => round($total * ($p[2] ?? 1), 2));

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'status' => 'ordered',
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $paid,
            'order_date' => $date,
            'expected_date' => $date->addDays(7),
            'payment_due_date' => today()->addDays($dueInDays),
        ]);

        foreach ($lines as [$product, $qty]) {
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $product->cost_price,
                'subtotal' => (float) $product->cost_price * $qty,
            ]);
        }

        foreach ($payments as [$method, $accountId, $amount]) {
            PurchasePayment::create([
                'purchase_order_id' => $order->id,
                'created_by' => $this->user->id,
                'payment_account_id' => $accountId,
                'amount' => round($total * ($amount ?? 1), 2),
                'method' => $method,
                'payment_date' => $date,
            ]);
        }
    }
}

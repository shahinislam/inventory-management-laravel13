<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

/**
 * Demo sales and purchases so the Due Report and Account Report have data:
 * paid, part-paid and fully-due records, some overdue, paid through the
 * seeded bank accounts, cards and wallets.
 *
 * Runs once — skipped if any invoice or purchase order already exists.
 */
class SampleTransactionSeeder extends Seeder
{
    private User $user;

    private Warehouse $warehouse;

    public function run(): void
    {
        if (Invoice::exists() || PurchaseOrder::exists()) {
            return;
        }

        $this->user = User::where('email', 'admin@inventory.com')->first() ?? User::first();
        $this->warehouse = Warehouse::getDefault() ?? Warehouse::first();

        $account = fn (string $name) => PaymentAccount::where('name', $name)->value('id');
        $products = Product::whereHas('warehouses', fn ($q) => $q->where('product_warehouse.quantity', '>', 20))
            ->orderBy('id')->take(4)->get()->values();
        $customers = Customer::orderBy('id')->get()->values();
        $suppliers = Supplier::orderBy('id')->get()->values();

        if (! $this->user || ! $this->warehouse || $products->count() < 4 || $customers->isEmpty() || $suppliers->isEmpty()) {
            return;
        }

        // ============ SALES ============

        // Fully paid by card.
        $this->sale($customers[0], [[$products[0], 2], [$products[1], 3]], daysAgo: 12,
            payments: [['card', $account('Company Visa'), null]]);

        // Fully paid by bank transfer.
        $this->sale($customers[1 % $customers->count()], [[$products[2], 5]], daysAgo: 8,
            payments: [['bank_transfer', $account('DBBL Current Account'), null]]);

        // Part paid by bKash, rest overdue.
        $this->sale($customers[2 % $customers->count()], [[$products[0], 4], [$products[3], 2]], daysAgo: 20,
            payments: [['bank_transfer', $account('bKash Merchant'), 0.4]], dueInDays: -5);

        // Nothing paid yet, due next week.
        $this->sale($customers[0], [[$products[1], 6]], daysAgo: 3,
            payments: [], dueInDays: 7);

        // Cash sale — no account.
        $this->sale(null, [[$products[3], 1]], daysAgo: 1,
            payments: [['cash', null, null]]);

        // ============ PURCHASES ============
        // Left in "ordered" (not received) so seeding does not change stock.

        // Fully paid by bank.
        $this->purchase($suppliers[0], [[$products[0], 20], [$products[2], 30]], daysAgo: 15,
            payments: [['bank_transfer', $account('City Bank Savings'), null]], dueInDays: 15);

        // Part paid by card, rest overdue.
        $this->purchase($suppliers[1 % $suppliers->count()], [[$products[1], 40]], daysAgo: 25,
            payments: [['card', $account('Amex Corporate'), 0.5]], dueInDays: -10);

        // Nothing paid, due later this month.
        $this->purchase($suppliers[2 % $suppliers->count()], [[$products[3], 25]], daysAgo: 4,
            payments: [], dueInDays: 20);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     * @param  array<int, array{0: string, 1: ?int, 2: ?float}>  $payments  [method, account id, share of the total (null = all)]
     */
    private function sale(?Customer $customer, array $lines, int $daysAgo, array $payments, ?int $dueInDays = null): void
    {
        $date = today()->subDays($daysAgo);
        $total = collect($lines)->sum(fn ($l) => (float) $l[0]->selling_price * $l[1]);
        $paid = collect($payments)->sum(fn ($p) => round($total * ($p[2] ?? 1), 2));
        $due = max(0, $total - $paid);

        $invoice = Invoice::create([
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer?->id,
            'created_by' => $this->user->id,
            'customer_name' => $customer?->name ?? 'Walk-in Customer',
            'customer_phone' => $customer?->phone,
            'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'sent'),
            'payment_method' => $payments[0][0] ?? null,
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $paid,
            'due_amount' => $due,
            'invoice_date' => $date,
            'due_date' => $dueInDays !== null ? today()->addDays($dueInDays) : null,
            'paid_date' => $due <= 0 ? $date : null,
        ]);

        foreach ($lines as [$product, $qty]) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $qty,
                'unit_price' => $product->selling_price,
                'subtotal' => (float) $product->selling_price * $qty,
            ]);

            app(InventoryService::class)->remove(
                productId: $product->id,
                warehouseId: $this->warehouse->id,
                quantity: $qty,
                type: 'sale',
                extra: ['reference' => $invoice],
            );
        }

        foreach ($payments as [$method, $accountId, $amount]) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'created_by' => $this->user->id,
                'payment_account_id' => $accountId,
                'amount' => round($total * ($amount ?? 1), 2),
                'method' => $method,
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

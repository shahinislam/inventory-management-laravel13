<?php

namespace App\Livewire\Invoices;

use App\Concerns\HandlesBarcodeScans;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\PricingService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InvoiceForm extends Component
{
    use HandlesBarcodeScans;

    public ?Invoice $invoice = null;

    public ?int $customer_id = null;

    public string $customerSearch = '';

    public string $customer_name = '';

    public string $customer_email = '';

    public string $customer_phone = '';

    public string $customer_address = '';

    public ?int $warehouse_id = null;

    public string $invoice_date = '';

    public string $due_date = '';

    public string $payment_method = 'cash';

    public ?int $payment_account_id = null;

    /** Mobile-wallet transaction ID when paid that way. */
    public string $payment_reference = '';

    public string $tax = '0';

    public string $discount = '0';

    /** Member reward given at the POS; kept as-is when the invoice is edited here. */
    public float $membershipDiscount = 0;

    public bool $hasCourier = false;

    /** Billed to the customer; part of the invoice total. */
    public string $courierCharge = '0';

    /** Paid to the courier; internal, never shown on the customer's copy. */
    public string $courierCost = '0';

    public string $notes = '';

    public array $items = [];

    /**
     * Whether line prices contain tax. New invoices follow Settings → Tax; a
     * saved draft keeps the mode it was priced in, even if the setting changes.
     */
    #[Locked]
    public bool $pricesIncludeTax = false;

    public string $productSearch = '';

    public function mount(?Invoice $invoice = null): void
    {
        $this->invoice_date = now()->format('Y-m-d');
        $this->warehouse_id = Warehouse::getDefault()?->id;

        // Return notes and parked POS sales are not edited here.
        if ($invoice?->exists && ($invoice->isReturn() || $invoice->is_held)) {
            $this->redirect(route('invoices.show', $invoice), navigate: true);

            return;
        }

        // Re-saving rebuilds the lines and the paid/due figures from scratch, so
        // once money, returns or stock are attached it would double-deduct stock,
        // wipe payments or reopen returned goods. Payments go through the invoice
        // page; corrections through a return.
        if ($invoice?->exists && $this->hasLockedHistory($invoice)) {
            session()->flash('error', 'This invoice already has payments, returns or stock taken out, so it can no longer be edited. Record payments from the invoice page, or process a return.');
            $this->redirect(route('invoices.show', $invoice), navigate: true);

            return;
        }

        $this->pricesIncludeTax = $invoice?->exists
            ? (bool) $invoice->prices_include_tax
            : PricingService::pricesIncludeTax();

        if ($invoice?->exists) {
            $this->invoice = $invoice->load('items.product');
            $this->customer_id = $invoice->customer_id;
            $this->customer_name = $invoice->customer_name;
            $this->customer_email = $invoice->customer_email ?? '';
            $this->customer_phone = $invoice->customer_phone ?? '';
            $this->customer_address = $invoice->customer_address ?? '';
            $this->warehouse_id = $invoice->warehouse_id;
            $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
            $this->due_date = $invoice->due_date?->format('Y-m-d') ?? '';
            $this->payment_method = $invoice->payment_method ?? 'cash';
            // See PurchaseForm::mount() — string-typed properties, nullable columns.
            // The stored tax includes the line tax, which is recalculated from the
            // lines; only the manually added tax goes back into the field.
            $this->tax = (string) max(0, round((float) $invoice->tax - (float) $invoice->items->sum('tax_amount'), 2));
            $this->discount = (string) ($invoice->discount ?? '0');
            $this->membershipDiscount = (float) $invoice->membership_discount;
            $this->courierCharge = (string) ($invoice->courier_charge ?? '0');
            $this->courierCost = (string) ($invoice->courier_cost ?? '0');
            $this->hasCourier = (float) $this->courierCharge > 0 || (float) $this->courierCost > 0;
            $this->notes = $invoice->notes ?? '';

            $this->items = $invoice->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'unit' => $item->product?->unit,
                'loose' => (bool) $item->product?->isLoose(),
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'tax_rate' => (string) $item->tax_rate,
                'discount' => (string) $item->discount,
                'is_gift' => (bool) $item->is_gift,
            ])->toArray();
        }
    }

    public function getCustomerResultsProperty()
    {
        if (empty($this->customerSearch) || $this->customer_id) {
            return collect();
        }

        $phone = Customer::normalizePhone($this->customerSearch) ?? $this->customerSearch;

        return Customer::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->customerSearch}%")
                ->orWhere('phone', 'like', "%{$phone}%")
            )->limit(6)->get();
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        $this->customer_id = $customer->id;
        $this->customer_name = $customer->name;
        $this->customer_email = $customer->email ?? '';
        $this->customer_phone = $customer->phone ?? '';
        $this->customer_address = $customer->full_address ?? '';
        $this->customerSearch = $customer->name;
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->customer_name = '';
        $this->customer_email = '';
        $this->customer_phone = '';
        $this->customer_address = '';
    }

    public function getProductResultsProperty()
    {
        if (empty($this->productSearch)) {
            return collect();
        }

        return Product::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->productSearch}%")
                ->orWhere('sku', 'like', "%{$this->productSearch}%")
                ->orWhere('barcode', 'like', "%{$this->productSearch}%")
            )->limit(8)->get();
    }

    /**
     * Stock on hand in the selected warehouse, keyed by product id, for every
     * product on the invoice or in the current search results. One query.
     *
     * @return array<int, int>
     */
    public function getStockLevelsProperty(): array
    {
        $ids = collect($this->items)->pluck('product_id')
            ->merge($this->productResults->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! $this->warehouse_id) {
            return [];
        }

        return DB::table('product_warehouse')
            ->where('warehouse_id', $this->warehouse_id)
            ->whereIn('product_id', $ids)
            ->pluck('quantity', 'product_id')
            ->map(fn ($qty) => round((float) $qty, 3))
            ->all();
    }

    /**
     * Lines asking for more than the warehouse holds.
     *
     * An invoice that is already paid had its stock deducted when it was paid,
     * so comparing it against what is left now would report false shortages.
     *
     * @return array<int, array{name: string, requested: int, available: int}>
     */
    public function getStockShortagesProperty(): array
    {
        if ($this->invoice?->exists && $this->invoice->status === 'paid') {
            return [];
        }

        $levels = $this->stockLevels;
        $shortages = [];

        foreach ($this->items as $item) {
            $requested = round((float) ($item['quantity'] ?: 0), 3);
            $available = $levels[$item['product_id']] ?? 0;

            if ($requested > $available) {
                $shortages[$item['product_id']] = [
                    'name' => $item['name'],
                    'requested' => $requested,
                    'available' => $available,
                ];
            }
        }

        return $shortages;
    }

    public function selectHighlighted(int $index): void
    {
        $product = $this->productResults->values()->get($index);
        if ($product) {
            $this->addProduct($product->id);
            $this->productSearch = '';
        }
    }

    public function addProduct(int $id): void
    {
        $product = Product::find($id);
        if (! $product) {
            return;
        }

        foreach ($this->items as $i => $item) {
            if ($item['product_id'] === $id) {
                $this->items[$i]['quantity'] = (string) round((float) $item['quantity'] + 1, 3);
                $this->productSearch = '';

                return;
            }
        }

        $discount = app(PricingService::class)->resolveDiscount($product);

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'loose' => $product->isLoose(),
            'quantity' => '1',
            'unit_price' => (string) $product->selling_price,
            'tax_rate' => (string) $product->tax_rate,
            'discount' => (string) $discount->perUnit,
            'promotion_id' => $discount->promotionId,
            'promotion_label' => $discount->label,
        ];

        $this->productSearch = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Net value of the goods: quantity x price, less per-unit discounts.
     * Line tax is deliberately excluded — see $this->itemTax.
     */
    public function getSubtotalProperty(): float
    {
        return round(array_reduce($this->items, fn ($sum, $item) => $sum + $this->lineAmounts($item)['net'], 0), 2);
    }

    /** Tax accumulated from the per-line tax_rate on each item. */
    public function getItemTaxProperty(): float
    {
        return round(array_reduce($this->items, fn ($sum, $item) => $sum + $this->lineAmounts($item)['tax'], 0), 2);
    }

    /** The goods value as the invoice lists it: tax included when prices contain it. */
    public function getGoodsTotalProperty(): float
    {
        return $this->pricesIncludeTax ? round($this->subtotal + $this->itemTax, 2) : $this->subtotal;
    }

    /** @return array{net: float, tax: float, gross: float} */
    private function lineAmounts(array $item): array
    {
        return PricingService::lineAmounts(
            (float) ($item['unit_price'] ?: 0), (float) ($item['discount'] ?: 0), (float) ($item['quantity'] ?: 0),
            (float) ($item['tax_rate'] ?: 0), $this->pricesIncludeTax,
        );
    }

    /**
     * Total tax charged: per-line tax plus the manually entered tax surcharge.
     * This is what gets stored on the invoice, so the tax shown to the customer
     * matches the tax actually collected.
     */
    public function getTaxTotalProperty(): float
    {
        return $this->itemTax + (float) ($this->tax ?: 0);
    }

    /*
     * Mirroring the courier charge into the cost happens in the browser
     * (invoice-form.blade.php). Inputs are deferred, so the server gets a batch
     * of updates in no guaranteed order and a server-side mirror could overwrite
     * a cost typed by hand.
     */
    public function updatedHasCourier(): void
    {
        if (! $this->hasCourier) {
            $this->courierCharge = '0';
            $this->courierCost = '0';
        }
    }

    /** The charge, or zero when the courier option is switched off. */
    public function getCourierChargeValueProperty(): float
    {
        return $this->hasCourier ? (float) ($this->courierCharge ?: 0) : 0.0;
    }

    public function getCourierCostValueProperty(): float
    {
        return $this->hasCourier ? (float) ($this->courierCost ?: 0) : 0.0;
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - (float) ($this->discount ?: 0) - $this->membershipDiscount + $this->taxTotal + $this->courierChargeValue);
    }

    protected function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:200',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|required_with:customer_id|string|max:20',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'courierCharge' => 'nullable|numeric|min:0',
            'courierCost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|numeric|min:0.001',
            // Counted goods (pcs, box) are sold whole; only loose goods take fractions.
            ...collect($this->items)
                ->reject(fn ($item) => ! empty($item['loose']))
                ->mapWithKeys(fn ($item, $i) => ["items.{$i}.quantity" => 'required|numeric|integer|min:1'])
                ->all(),
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'customer_phone.required_with' => 'A member sale needs the customer\'s phone number.',
        ];
    }

    public function saveDraft(): void
    {
        $this->validate();
        $this->persist('draft');
        session()->flash('success', 'Invoice saved as draft!');
        $this->redirect(route('invoices.index'), navigate: true);
    }

    public function saveAndSend(): void
    {
        $this->validate();

        // Sending hands the goods over, so it takes the stock out (as a POS
        // credit sale does). Refuse up front rather than throw mid-save.
        if ($this->stockShortages !== []) {
            $this->addError('stock', 'Not enough stock to send this invoice. Reduce the quantities or save it as a draft.');

            return;
        }

        $invoice = $this->persist('sent');
        session()->flash('success', 'Invoice saved and marked as sent!');
        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    public function saveAsPaid(): void
    {
        $this->validate();

        // Marking paid takes the stock out of the warehouse. Refuse up front with
        // a readable message rather than letting the deduction throw mid-save.
        if ($this->stockShortages !== []) {
            $this->addError('stock', 'Not enough stock to mark this invoice paid. Reduce the quantities or save it as a draft.');

            return;
        }

        $this->validate(
            [
                'payment_method' => Payment::methodRule(),
                'payment_account_id' => PaymentAccount::rule($this->payment_method),
                'payment_reference' => Payment::needsReference($this->payment_method) ? 'required|string|max:100' : 'nullable|string|max:100',
            ],
            [
                'payment_account_id.required' => 'Select the account or card this was paid to.',
                'payment_reference.required' => 'Enter the '.wallet_label().' transaction ID.',
            ],
        );

        // The payment row is written inside persist()'s transaction, so a failure
        // there cannot leave a paid invoice with deducted stock and no payment.
        $invoice = $this->persist('paid');

        DashboardIndex::flushCache();
        session()->flash('success', 'Invoice saved and marked as paid!');
        $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    private function persist(string $status): Invoice
    {
        // Stock and promotion usage are committed once, when the invoice first
        // leaves draft (sent or paid). Paying it later, from the invoice page,
        // must not deduct again — and invoices with stock out cannot be re-edited.
        $wasAlreadyPaid = $this->invoice?->exists && $this->invoice->status === 'paid';
        $shouldCommitStock = $status !== 'draft' && ! $wasAlreadyPaid;

        return DB::transaction(function () use ($status, $wasAlreadyPaid, $shouldCommitStock) {
            $data = [
                'customer_id' => $this->customer_id,
                'warehouse_id' => $this->warehouse_id,
                'created_by' => $this->invoice?->created_by ?? auth()->id(),
                'customer_name' => $this->customer_name,
                'customer_email' => $this->customer_email ?: null,
                'customer_phone' => $this->customer_phone ?: null,
                'customer_address' => $this->customer_address ?: null,
                'status' => $status,
                'payment_method' => $this->payment_method ?: null,
                'prices_include_tax' => $this->pricesIncludeTax,
                'subtotal' => $this->subtotal,
                'tax' => $this->taxTotal,
                'discount' => (float) ($this->discount ?: 0),
                'membership_discount' => $this->customer_id ? $this->membershipDiscount : 0,
                'courier_charge' => $this->courierChargeValue,
                'courier_cost' => $this->courierCostValue,
                'total' => $this->total,
                'paid_amount' => $status === 'paid' ? $this->total : 0,
                'due_amount' => $status === 'paid' ? 0 : $this->total,
                'invoice_date' => $this->invoice_date,
                'due_date' => $this->due_date ?: null,
                'paid_date' => $status === 'paid' ? now() : null,
                'notes' => $this->notes ?: null,
            ];

            if ($this->invoice?->exists) {
                $this->invoice->update($data);
                $this->invoice->items()->delete();
                $invoice = $this->invoice;
            } else {
                $invoice = Invoice::create($data);
            }

            $inventory = app(InventoryService::class);

            foreach ($this->items as $item) {
                $amounts = $this->lineAmounts($item);

                // Deduct stock only on the way out of draft; that also tells us
                // what the goods cost (earliest-expiry batch first).
                $unitCost = (float) (Product::find($item['product_id'])?->cost_price ?? 0);
                if ($shouldCommitStock) {
                    $unitCost = $inventory->remove(
                        productId: (int) $item['product_id'],
                        warehouseId: (int) $this->warehouse_id,
                        quantity: (float) $item['quantity'],
                        type: 'sale',
                        extra: ['reference' => $invoice],
                    )['unit_cost'];
                }

                InvoiceItem::create([
                    'unit_cost' => $unitCost,
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $item['discount'],
                    'subtotal' => $amounts['gross'],
                    'tax_amount' => $amounts['tax'],
                    'is_gift' => ! empty($item['is_gift']),
                ]);

                if (! empty($item['promotion_id']) && $status !== 'draft' && ! $wasAlreadyPaid) {
                    Promotion::where('id', $item['promotion_id'])
                        ->where(fn ($q) => $q
                            ->whereNull('usage_limit')
                            ->orWhereColumn('used_count', '<', 'usage_limit'))
                        ->increment('used_count');
                }
            }

            // Record the payment in the same transaction as the invoice it pays for.
            if ($status === 'paid' && ! $wasAlreadyPaid) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'created_by' => auth()->id(),
                    'payment_account_id' => PaymentAccount::requiredFor($this->payment_method) ? $this->payment_account_id : null,
                    'amount' => $this->total,
                    'method' => $this->payment_method,
                    'reference' => $this->payment_reference ?: null,
                    'shift_id' => app(ShiftService::class)->currentFor(auth()->user())?->id,
                    'status' => 'completed',
                    'payment_date' => now(),
                ]);
            }

            return $invoice;
        });
    }

    public function updatedPaymentMethod(): void
    {
        $accounts = PaymentAccount::forMethod($this->payment_method)->get();
        $this->payment_account_id = $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    protected function handleScan(string $code): bool
    {
        $product = $this->findScannedProduct($code);

        if (! $product) {
            return false;
        }

        $this->addProduct($product->id); // adds 1 more if already listed

        return true;
    }

    /** Payments, returns or stock movements are tied to this invoice. */
    private function hasLockedHistory(Invoice $invoice): bool
    {
        return $invoice->payments()->exists()
            || (float) $invoice->returned_amount > 0
            || $invoice->returns()->exists()
            || StockMovement::where('reference_type', Invoice::class)->where('reference_id', $invoice->id)->exists();
    }

    public function render()
    {
        $warehouses = Warehouse::active()->get();
        $paymentAccounts = PaymentAccount::forMethod($this->payment_method)->get();

        return view('livewire.invoices.invoice-form', compact('warehouses', 'paymentAccounts'))
            ->layout('layouts.app', ['title' => $this->invoice?->exists ? 'Edit Invoice' : 'New Invoice']);
    }
}

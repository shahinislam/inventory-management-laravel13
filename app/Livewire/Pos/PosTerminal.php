<?php

namespace App\Livewire\Pos;

use App\Concerns\HandlesBarcodeScans;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Pos\Concerns\HoldsSales;
use App\Livewire\Pos\Concerns\RequiresShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MembershipReward;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\MembershipService;
use App\Services\PricingService;
use App\Services\ResolvedDiscount;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosTerminal extends Component
{
    use HandlesBarcodeScans, HoldsSales, RequiresShift;

    public string $search = '';

    public int $highlightIndex = 0;

    public array $cart = [];

    public ?int $customer_id = null;

    public string $customerSearch = '';

    public int $customerHighlight = 0;

    public bool $showCustomerSearch = false;

    public ?int $warehouse_id = null;

    public string $discount = '0';

    public string $tax = '0';

    public bool $hasCourier = false;

    /** Billed to the customer; part of the invoice total. */
    public string $courierCharge = '0';

    /** Paid to the courier; internal, never shown on the customer's copy. */
    public string $courierCost = '0';

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    /** Bank account or card the payment went through (card / bank only). */
    public ?int $paymentAccountId = null;

    /** Sell on credit: take part (or none) of the total now, the rest is due. */
    public bool $payLater = false;

    public string $paidNow = '0';

    public bool $showPaymentModal = false;

    public bool $showSuccessModal = false;

    public ?Invoice $lastInvoice = null;

    /**
     * Member rewards the cashier chose to give on this sale, keyed by reward id:
     * ['name', 'type', 'amount' (editable, discounts only), 'edited'].
     */
    public array $appliedRewards = [];

    /** Sum of the applied discount rewards; read by the Alpine totals. */
    public float $membershipDiscount = 0;

    public bool $showRegisterCustomer = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    /** Loose goods (rice by kg) are weighed: the cashier types the weight here. */
    public bool $showWeighModal = false;

    public ?int $weighProductId = null;

    public string $weighQuantity = '';

    /** bKash / Nagad transaction ID, card slip number, etc. */
    public string $paymentReference = '';

    /** Pay one bill with several methods (part cash, part bKash). */
    public bool $splitMode = false;

    /** @var array<int, array{method: string, account_id: ?int, amount: string, reference: string}> */
    public array $splits = [];

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::getDefault()?->id;
    }

    // ============ PRODUCT SEARCH ============

    public function updatedSearch(): void
    {
        // Scanner input is handled by HandlesBarcodeScans, not by this box.
        $this->highlightIndex = 0;
    }

    public function getSearchResultsProperty()
    {
        if (trim($this->search) === '') {
            return collect();
        }

        // Only show products that are in stock in the warehouse being sold from.
        return Product::where('status', 'active')
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%");
            })
            ->whereHas('warehouses', fn ($q) => $q
                ->where('warehouses.id', $this->warehouse_id)
                ->where('product_warehouse.quantity', '>', 0))
            ->limit(8)
            ->get();
    }

    /**
     * How much of this product is on hand in the warehouse currently selected.
     */
    private function stockHere(int $productId): float
    {
        return app(InventoryService::class)->stockIn($productId, $this->warehouse_id);
    }

    /**
     * Switching warehouse invalidates the cart: the quantities and availability
     * were all taken from the previous location.
     */
    public function updatedWarehouseId(): void
    {
        if ($this->cart !== []) {
            $this->cart = [];
            $this->resetRewards();
            session()->flash('error', 'Cart cleared — stock differs between warehouses.');
        }

        $this->search = '';
    }

    public function moveHighlight(int $direction): void
    {
        $count = $this->searchResults->count();
        if ($count === 0) {
            return;
        }

        $this->highlightIndex = ($this->highlightIndex + $direction + $count) % $count;
    }

    public function selectHighlighted(int $index = 0): void
    {
        $results = $this->searchResults;
        if ($results->count() === 0) {
            return;
        }

        $product = $results->values()->get($index);
        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        }
    }

    // ============ CART ============

    /**
     * Put a product in the cart. Loose goods (sold by kg/ltr) need a weight, so
     * without one they open the weigh prompt instead.
     */
    public function addToCart(int $productId, ?float $quantity = null): void
    {
        $product = Product::find($productId);
        $available = $this->stockHere($productId);

        if (! $product || $available <= 0) {
            return;
        }

        if ($product->isLoose() && $quantity === null) {
            $this->weighProductId = $product->id;
            $this->weighQuantity = '';
            $this->resetErrorBag('weighQuantity');
            $this->showWeighModal = true;

            return;
        }

        $quantity ??= 1.0;

        foreach ($this->cart as $index => $item) {
            // A reward gift of the same product is its own line.
            if ($item['product_id'] === $productId && empty($item['is_gift'])) {
                $this->cart[$index]['quantity'] = round(min($item['quantity'] + $quantity, $available), 3);
                $this->cart[$index]['max_quantity'] = $available;
                $this->syncRewards();

                return;
            }
        }

        $discount = app(PricingService::class)->resolveDiscount($product);
        $this->cart[] = $this->cartLine($product, min($quantity, $available), $available, $discount);

        $this->syncRewards();
    }

    private function cartLine(Product $product, float $quantity, float $available, ResolvedDiscount $discount): array
    {
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'loose' => $product->isLoose(),
            'price' => (float) $product->selling_price,
            'tax_rate' => (float) $product->tax_rate,
            'discount' => $discount->perUnit,
            'promotion_id' => $discount->promotionId,
            'promotion_label' => $discount->label,
            'quantity' => round($quantity, 3),
            'max_quantity' => $available,
        ];
    }

    public function getWeighProductProperty(): ?Product
    {
        return $this->weighProductId ? Product::find($this->weighProductId) : null;
    }

    /** The cashier typed the weight of a loose item. */
    public function confirmWeight(): void
    {
        $product = $this->weighProduct;
        if (! $product) {
            $this->showWeighModal = false;

            return;
        }

        $inCart = (float) collect($this->cart)->where('product_id', $product->id)->where('is_gift', '!=', true)->sum('quantity');
        $left = round($this->stockHere($product->id) - $inCart, 3);
        $this->weighQuantity = str_replace(',', '', $this->weighQuantity);

        $this->validate(
            ['weighQuantity' => 'required|numeric|min:0.001|max:'.max(0.001, $left)],
            [
                'weighQuantity.required' => 'Enter the weight from the scale.',
                'weighQuantity.min' => 'Enter a weight above zero.',
                'weighQuantity.max' => 'Only '.format_qty($left, $product->unit).' left in this warehouse.',
            ],
        );

        $this->showWeighModal = false;
        $this->weighProductId = null;
        $this->addToCart($product->id, round((float) $this->weighQuantity, 3));
        $this->weighQuantity = '';
    }

    public function quickAdd(int $productId): void
    {
        $this->addToCart($productId);
        $this->search = '';
        $this->highlightIndex = 0;
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->cart[$index]) || ! empty($this->cart[$index]['is_gift'])) {
            return;
        }
        $this->cart[$index]['quantity'] = round(min($this->cart[$index]['quantity'] + 1, $this->cart[$index]['max_quantity']), 3);
        $this->syncRewards();
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->cart[$index]) || ! empty($this->cart[$index]['is_gift'])) {
            return;
        }
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity'] = round($this->cart[$index]['quantity'] - 1, 3);
            $this->syncRewards();
        } else {
            $this->removeFromCart($index);
        }
    }

    public function updateQty(int $index, $value): void
    {
        if (! isset($this->cart[$index]) || ! empty($this->cart[$index]['is_gift'])) {
            return;
        }
        // Loose goods take any weight; counted goods stay whole.
        $value = (float) str_replace(',', '', (string) $value);
        $qty = empty($this->cart[$index]['loose'])
            ? max(1, min(floor($value), $this->cart[$index]['max_quantity']))
            : max(0.001, min(round($value, 3), $this->cart[$index]['max_quantity']));
        $this->cart[$index]['quantity'] = $qty;
        $this->syncRewards();
    }

    /** Quantities edited in the browser arrive here when a member is selected. */
    public function updatedCart(): void
    {
        $this->syncRewards();
    }

    public function removeFromCart(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        // Removing a gift line takes back the reward that put it there.
        if (! empty($this->cart[$index]['is_gift'])) {
            unset($this->appliedRewards[$this->cart[$index]['reward_id']]);
        }

        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->syncRewards();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->resetRewards();
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->discount = '0';
        $this->tax = '0';
        $this->amountReceived = '';
        $this->paymentMethod = 'cash';
        $this->paymentAccountId = null;
        $this->payLater = false;
        $this->paidNow = '0';
        $this->paymentReference = '';
        $this->splitMode = false;
        $this->splits = [];
        $this->resetCourier();
    }

    // ============ COURIER ============

    public function updatedHasCourier(): void
    {
        if (! $this->hasCourier) {
            $this->resetCourier();
        }
    }

    private function resetCourier(): void
    {
        $this->hasCourier = false;
        $this->courierCharge = '0';
        $this->courierCost = '0';
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

    // ============ TOTALS ============

    /**
     * Net value of the goods in the cart, excluding line tax.
     * Line tax is reported separately via $this->cartItemTax.
     */
    public function getCartSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']);
        }, 0);
    }

    /** Tax accumulated from each line's tax_rate. */
    public function getCartItemTaxProperty(): float
    {
        return array_reduce($this->cart, function ($sum, $item) {
            $lineTotal = ($item['price'] * $item['quantity']) - ($item['discount'] * $item['quantity']);

            return $sum + ($lineTotal * ($item['tax_rate'] / 100));
        }, 0);
    }

    /** Per-line tax plus the manually entered additional tax. */
    public function getCartTaxTotalProperty(): float
    {
        return $this->cartItemTax + (float) ($this->tax ?: 0);
    }

    public function getCartItemDiscountTotalProperty(): float
    {
        return array_reduce($this->cart, fn ($sum, $item) => $sum + ($item['discount'] * $item['quantity']), 0);
    }

    public function getCartTotalProperty(): float
    {
        $discount = (float) ($this->discount ?: 0) + $this->membershipDiscount;

        return max(0, $this->cartSubtotal - $discount + $this->cartTaxTotal + $this->courierChargeValue);
    }

    public function getChangeDueProperty(): float
    {
        return max(0, $this->amountReceivedValue() - $this->cartTotal);
    }

    /**
     * Parse the cash-received input into a number.
     *
     * A plain (float) cast is not enough: a cashier may type "1,100.00", and
     * PHP truncates at the comma, yielding 1.0 — which silently reads as
     * underpayment and blocks the sale.
     */
    private function amountReceivedValue(): float
    {
        return (float) str_replace(',', '', $this->amountReceived ?: '0');
    }

    private function paidNowValue(): float
    {
        return (float) str_replace(',', '', $this->paidNow ?: '0');
    }

    /** What is left owing when selling on credit. */
    public function getDueAfterPaymentProperty(): float
    {
        return $this->payLater ? max(0, $this->cartTotal - $this->paidNowValue()) : 0.0;
    }

    /** Accounts the selected method can be paid through. */
    public function getPaymentAccountsProperty()
    {
        return PaymentAccount::forMethod($this->paymentMethod)->get();
    }

    // ============ CUSTOMER ============

    public function getCustomerResultsProperty()
    {
        if (empty($this->customerSearch)) {
            return collect();
        }

        // Phones are stored without spaces or dashes; match typed input the same way.
        $phone = Customer::normalizePhone($this->customerSearch) ?? $this->customerSearch;

        return Customer::active()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->customerSearch}%")
                ->orWhere('phone', 'like', "%{$phone}%")
            )
            ->limit(6)
            ->get();
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        $this->customer_id = $customer->id;
        $this->customerSearch = $customer->name.($customer->phone ? " ({$customer->phone})" : '');
        $this->showCustomerSearch = false;
        $this->resetRewards();
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->customerSearch = '';
        $this->resetRewards();
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        return $this->customer_id ? Customer::find($this->customer_id) : null;
    }

    // ============ QUICK REGISTER ============

    /** Opens the register form, pre-filled from whatever was typed in the search. */
    public function openRegisterCustomer(): void
    {
        $typed = trim($this->customerSearch);
        $looksLikePhone = $typed !== '' && preg_match('/^[\d\s\-\+\(\)\.]+$/', $typed);

        $this->newCustomerPhone = $looksLikePhone ? $typed : '';
        $this->newCustomerName = $looksLikePhone ? '' : $typed;
        $this->resetErrorBag(['newCustomerName', 'newCustomerPhone']);
        $this->showRegisterCustomer = true;
    }

    public function registerCustomer(): void
    {
        $this->newCustomerPhone = Customer::normalizePhone($this->newCustomerPhone) ?? '';

        $this->validate([
            'newCustomerName' => 'required|string|max:200',
            'newCustomerPhone' => 'required|string|max:20|unique:customers,phone',
        ], [
            'newCustomerName.required' => 'Enter the customer\'s name.',
            'newCustomerPhone.required' => 'Enter a phone number — it identifies the member.',
            'newCustomerPhone.unique' => 'This phone number already belongs to a customer. Search for it instead.',
        ]);

        $customer = Customer::create([
            'name' => trim($this->newCustomerName),
            'phone' => $this->newCustomerPhone,
            'is_active' => true,
        ]);

        $this->showRegisterCustomer = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->selectCustomer($customer->id);
    }

    // ============ MEMBER REWARDS ============

    public function getMemberSpendProperty(): float
    {
        return $this->customer_id ? app(MembershipService::class)->customerSpend($this->customer_id) : 0.0;
    }

    /** Rules this member qualifies for on the current cart, best first. */
    public function getEligibleRewardsProperty()
    {
        $customer = $this->selectedCustomer;

        return $customer
            ? app(MembershipService::class)->eligibleRewards($customer, $this->cartSubtotal, $this->memberSpend)
            : collect();
    }

    public function getNextRewardProperty(): ?array
    {
        $customer = $this->selectedCustomer;

        return $customer
            ? app(MembershipService::class)->nextReward($customer, $this->cartSubtotal, $this->memberSpend)
            : null;
    }

    /** Why a gift reward cannot be given right now, or null when it can. */
    public function giftProblem(MembershipReward $reward): ?string
    {
        if (! $reward->isGift()) {
            return null;
        }
        if (! $reward->giftProduct || $reward->giftProduct->status !== 'active') {
            return 'Gift product is no longer available.';
        }

        $inCart = collect($this->cart)
            ->where('product_id', $reward->gift_product_id)
            ->where('is_gift', '!=', true)
            ->sum('quantity');

        $free = $this->stockHere($reward->gift_product_id) - $inCart;

        return $free < $reward->gift_quantity
            ? "Only {$free} {$reward->giftProduct->name} left in this warehouse."
            : null;
    }

    public function applyReward(int $rewardId): void
    {
        $reward = $this->eligibleRewards->firstWhere('id', $rewardId);

        if (! $reward || isset($this->appliedRewards[$rewardId])) {
            return;
        }

        if ($problem = $this->giftProblem($reward)) {
            session()->flash('error', $problem);

            return;
        }

        $this->appliedRewards[$rewardId] = [
            'name' => $reward->name,
            'type' => $reward->reward_type,
            'amount' => $reward->isGift() ? '0' : number_format($reward->discountFor($this->cartSubtotal), 2, '.', ''),
            'edited' => false,
        ];

        if ($reward->isGift()) {
            $product = $reward->giftProduct;
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'price' => 0.0,
                'tax_rate' => 0.0,
                'discount' => 0.0,
                'promotion_id' => null,
                'promotion_label' => 'Member gift · '.$reward->name,
                'quantity' => $reward->gift_quantity,
                'max_quantity' => $reward->gift_quantity,
                'is_gift' => true,
                'reward_id' => $reward->id,
            ];
        }

        $this->recalculateMembershipDiscount();
    }

    public function removeReward(int $rewardId): void
    {
        unset($this->appliedRewards[$rewardId]);
        $this->removeGiftLines([$rewardId]);
        $this->recalculateMembershipDiscount();
    }

    /** The cashier typed a different discount amount for an applied reward. */
    public function updatedAppliedRewards($value, string $key): void
    {
        [$rewardId] = explode('.', $key);

        if (isset($this->appliedRewards[$rewardId])) {
            $amount = max(0, (float) str_replace(',', '', (string) $this->appliedRewards[$rewardId]['amount']));
            $this->appliedRewards[$rewardId]['amount'] = number_format($amount, 2, '.', '');
            $this->appliedRewards[$rewardId]['edited'] = true;
        }

        $this->recalculateMembershipDiscount();
    }

    /**
     * Keep applied rewards valid as the cart changes: drop any the member no
     * longer qualifies for, and re-price untouched percentage discounts.
     */
    private function syncRewards(): void
    {
        $this->forgetCartComputed();

        if ($this->appliedRewards === []) {
            $this->membershipDiscount = 0;

            return;
        }

        $eligible = $this->eligibleRewards->keyBy('id');
        $dropped = [];

        foreach ($this->appliedRewards as $id => $applied) {
            $reward = $eligible->get($id);

            if (! $reward) {
                $dropped[] = $id;
                unset($this->appliedRewards[$id]);

                continue;
            }

            if (! $reward->isGift() && ! $applied['edited']) {
                $this->appliedRewards[$id]['amount'] = number_format($reward->discountFor($this->cartSubtotal), 2, '.', '');
            }
        }

        if ($dropped !== []) {
            $this->removeGiftLines($dropped);
        }

        $this->recalculateMembershipDiscount();
    }

    private function removeGiftLines(array $rewardIds): void
    {
        $this->cart = array_values(array_filter(
            $this->cart,
            fn ($item) => empty($item['is_gift']) || ! in_array($item['reward_id'], $rewardIds),
        ));
    }

    /** Never discount more than the goods are worth. */
    private function recalculateMembershipDiscount(): void
    {
        $sum = collect($this->appliedRewards)
            ->where('type', '!=', 'gift')
            ->sum(fn ($r) => (float) $r['amount']);

        $this->membershipDiscount = round(min($sum, max(0, $this->cartSubtotal)), 2);
        unset($this->cartTotal);
    }

    private function resetRewards(): void
    {
        $this->removeGiftLines(array_keys($this->appliedRewards));
        $this->appliedRewards = [];
        $this->membershipDiscount = 0;
        $this->forgetCartComputed();
    }

    /**
     * Livewire memoises getXxxProperty() values for the request; drop the ones
     * derived from the cart or customer so the next read sees the change.
     */
    private function forgetCartComputed(): void
    {
        unset(
            $this->cartSubtotal, $this->cartItemTax, $this->cartTaxTotal,
            $this->cartItemDiscountTotal, $this->cartTotal,
            $this->selectedCustomer, $this->memberSpend, $this->eligibleRewards, $this->nextReward,
        );
    }

    // ============ PAYMENT / CHECKOUT ============

    public function openPaymentModal(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');

            return;
        }
        $this->syncRewards();
        // No thousands separator: this populates a numeric input that is later
        // cast with (float), and "1,100.00" would cast to 1.0.
        $this->amountReceived = number_format($this->cartTotal, 2, '.', '');
        $this->setPaymentMethod($this->paymentMethod);
        $this->showPaymentModal = true;
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        $this->splitMode = false;
        $this->paymentReference = '';
        $this->resetErrorBag(['paymentAccountId', 'paymentReference', 'splits']);

        // Pre-select when there is only one account to choose from.
        $accounts = $this->paymentAccounts;
        $this->paymentAccountId = $accounts->count() === 1 ? $accounts->first()->id : null;

        if ($method === 'cash') {
            $this->amountReceived = number_format($this->cartTotal, 2, '.', '');
        }
    }

    // ============ SPLIT PAYMENT ============

    /** Methods a bill can be split across. */
    public const SPLIT_METHODS = ['cash', 'card', 'mobile_banking', 'bank_transfer'];

    public function enableSplit(): void
    {
        $this->payLater = false;
        $this->splitMode = true;
        $this->resetErrorBag(['splits', 'paymentAccountId', 'paymentReference']);
        $this->splits = [
            ['method' => 'cash', 'account_id' => null, 'amount' => '', 'reference' => ''],
            ['method' => 'mobile_banking', 'account_id' => $this->defaultAccount('mobile_banking'), 'amount' => '', 'reference' => ''],
        ];
    }

    public function disableSplit(): void
    {
        $this->splitMode = false;
        $this->splits = [];
        $this->resetErrorBag('splits');
    }

    public function addSplitRow(): void
    {
        $this->splits[] = ['method' => 'card', 'account_id' => $this->defaultAccount('card'), 'amount' => $this->splitRemainingFormatted(), 'reference' => ''];
    }

    public function removeSplitRow(int $index): void
    {
        unset($this->splits[$index]);
        $this->splits = array_values($this->splits);
    }

    /** Changing a row's method picks that method's only account, if there is one. */
    public function updatedSplits($value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);

        if ($field === 'method' && isset($this->splits[$index])) {
            $this->splits[$index]['account_id'] = $this->defaultAccount((string) $value);
            $this->splits[$index]['reference'] = '';
        }
    }

    /** Fill the last row with whatever is still unpaid. */
    public function fillSplitRemaining(int $index): void
    {
        if (isset($this->splits[$index])) {
            $this->splits[$index]['amount'] = '';
            $this->splits[$index]['amount'] = $this->splitRemainingFormatted();
        }
    }

    public function getSplitPaidProperty(): float
    {
        return round(collect($this->splits)->sum(fn ($r) => (float) str_replace(',', '', (string) $r['amount'])), 2);
    }

    public function getSplitRemainingProperty(): float
    {
        return round($this->cartTotal - $this->splitPaid, 2);
    }

    private function splitRemainingFormatted(): string
    {
        return number_format(max(0, $this->splitRemaining), 2, '.', '');
    }

    private function defaultAccount(string $method): ?int
    {
        $accounts = PaymentAccount::forMethod($method)->get();

        return $accounts->count() === 1 ? $accounts->first()->id : null;
    }

    /** Check the split rows add up and each has what its method needs. Null when invalid. */
    private function validatedSplits(float $total): ?array
    {
        $rows = collect($this->splits)
            ->map(fn ($r) => $r + ['value' => round((float) str_replace(',', '', (string) $r['amount']), 2)])
            ->filter(fn ($r) => $r['value'] > 0)
            ->values();

        $error = match (true) {
            $rows->count() < 2 => 'Enter amounts for at least two methods, or turn split off.',
            abs($rows->sum('value') - $total) > 0.009 => 'The parts add up to '.money($rows->sum('value')).' but the bill is '.money($total).'.',
            default => null,
        };

        foreach ($rows as $r) {
            if ($error) {
                break;
            }
            if (! in_array($r['method'], self::SPLIT_METHODS, true)) {
                $error = 'Choose a method for every part.';
            } elseif (PaymentAccount::requiredFor($r['method']) && ! PaymentAccount::forMethod($r['method'])->whereKey($r['account_id'])->exists()) {
                $error = 'Select the account for the '.Payment::methodLabel($r['method']).' part.';
            } elseif (Payment::needsReference($r['method']) && blank($r['reference'])) {
                $error = 'Enter the transaction ID for the bKash / Nagad part.';
            }
        }

        if ($error) {
            $this->addError('splits', $error);

            return null;
        }

        return $rows->map(fn ($r) => [
            'method' => $r['method'],
            'account_id' => PaymentAccount::requiredFor($r['method']) ? $r['account_id'] : null,
            'amount' => $r['value'],
            'reference' => $r['reference'] ?: null,
        ])->all();
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) {
            return;
        }

        if (! $shift = $this->shiftOrFail()) {
            return;
        }

        // Re-check rewards against the final cart before anything is charged.
        $this->syncRewards();
        $total = round($this->cartTotal, 2);
        $method = $this->paymentMethod;

        if ($this->splitMode && ! $this->payLater) {
            if (null === $payments = $this->validatedSplits($total)) {
                return;
            }
            $paid = $total;
            $method = 'split';
        } elseif ($this->payLater) {
            // A credit sale must be traceable to the customer who owes it.
            $this->paidNow = str_replace(',', '', $this->paidNow ?: '0');
            $this->validate([
                'customer_id' => 'required|exists:customers,id',
                'paidNow' => 'required|numeric|min:0|max:'.$total,
            ], [
                'customer_id.required' => 'Select a customer to sell on due.',
                'paidNow.max' => 'Paid amount cannot be more than the total.',
            ]);
            $paid = $this->paidNowValue();
        } else {
            if ($this->paymentMethod === 'cash' && $this->amountReceivedValue() < $total) {
                session()->flash('error', 'Received amount is less than total.');

                return;
            }
            $paid = $total;
        }

        $due = max(0, round($total - $paid, 2));

        if ($method !== 'split') {
            if ($paid > 0) {
                $this->validate(
                    [
                        'paymentAccountId' => PaymentAccount::rule($this->paymentMethod),
                        'paymentReference' => Payment::needsReference($this->paymentMethod) ? 'required|string|max:100' : 'nullable|string|max:100',
                    ],
                    [
                        'paymentAccountId.required' => 'Select the account or card this was paid to.',
                        'paymentReference.required' => 'Enter the bKash / Nagad transaction ID.',
                    ],
                );
            }

            $payments = $paid > 0 ? [[
                'method' => $this->paymentMethod,
                'account_id' => PaymentAccount::requiredFor($this->paymentMethod) ? $this->paymentAccountId : null,
                'amount' => $paid,
                'reference' => $this->paymentReference ?: null,
            ]] : [];
        }

        $invoice = DB::transaction(function () use ($total, $paid, $due, $method, $payments, $shift) {
            $customer = $this->customer_id ? Customer::find($this->customer_id) : null;

            $invoice = Invoice::create([
                'warehouse_id' => $this->warehouse_id,
                'customer_id' => $this->customer_id,
                'created_by' => auth()->id(),
                'shift_id' => $shift->id,
                'customer_name' => $customer?->name ?? 'Walk-in Customer',
                'customer_email' => $customer?->email,
                'customer_phone' => $customer?->phone,
                'customer_address' => $customer?->full_address,
                'status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'sent'),
                'payment_method' => $method,
                'subtotal' => $this->cartSubtotal,
                'tax' => $this->cartTaxTotal,
                'discount' => (float) ($this->discount ?: 0),
                'membership_discount' => $customer ? $this->membershipDiscount : 0,
                'courier_charge' => $this->courierChargeValue,
                'courier_cost' => $this->courierCostValue,
                'total' => $total,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'invoice_date' => now(),
                'paid_date' => $due <= 0 ? now() : null,
            ]);

            $inventory = app(InventoryService::class);

            foreach ($this->cart as $item) {
                // Takes the stock out of this terminal's warehouse, earliest
                // expiry first. Throws (and rolls the whole sale back) if the
                // stock is no longer there. Returns the cost of what was taken.
                $taken = $inventory->remove(
                    productId: $item['product_id'],
                    warehouseId: $this->warehouse_id,
                    quantity: (float) $item['quantity'],
                    type: 'sale',
                    extra: ['reference' => $invoice],
                );

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'unit_cost' => $taken['unit_cost'],
                    'tax_rate' => $item['tax_rate'],
                    'discount' => $item['discount'],
                    'subtotal' => $this->lineSubtotal($item),
                    'is_gift' => ! empty($item['is_gift']),
                ]);

                $this->consumePromotion($item);
            }

            // One payment row per method; none on a full-due sale.
            foreach ($payments as $payment) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'created_by' => auth()->id(),
                    'shift_id' => $shift->id,
                    'payment_account_id' => $payment['account_id'],
                    'amount' => $payment['amount'],
                    'method' => $payment['method'],
                    'reference' => $payment['reference'],
                    'status' => 'completed',
                    'payment_date' => now(),
                ]);
            }

            // Log each member reward given on this sale.
            if ($customer && $this->appliedRewards !== []) {
                $membership = app(MembershipService::class);
                $rewards = MembershipReward::whereIn('id', array_keys($this->appliedRewards))->get()->keyBy('id');

                foreach ($this->appliedRewards as $id => $applied) {
                    if ($reward = $rewards->get($id)) {
                        $membership->recordRedemption($invoice, $customer, $reward, (float) $applied['amount']);
                    }
                }
            }

            // Update customer stats
            if ($customer) {
                $customer->increment('total_orders');
                $customer->increment('total_purchases', $total);
            }

            return $invoice;
        });

        DashboardIndex::flushCache();

        // Members get an SMS receipt when that is switched on in Settings.
        app(SmsService::class)->saleReceipt($invoice);
        app(SmsService::class)->rewardsGiven($invoice);

        $this->lastInvoice = $invoice;
        $this->showPaymentModal = false;
        $this->showSuccessModal = true;
        $this->clearCart();
    }

    /** Line total after per-unit discount, plus this line's tax. */
    private function lineSubtotal(array $item): float
    {
        $afterDiscount = ($item['price'] - $item['discount']) * $item['quantity'];

        return $afterDiscount + ($afterDiscount * $item['tax_rate'] / 100);
    }

    /**
     * Record one use of the promotion attached to this line.
     *
     * The usage_limit is re-checked in the WHERE clause rather than in PHP, so
     * concurrent sales cannot push used_count past the limit.
     */
    private function consumePromotion(array $item): void
    {
        if (empty($item['promotion_id'])) {
            return;
        }

        Promotion::where('id', $item['promotion_id'])
            ->where(fn ($q) => $q
                ->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');
    }

    public function newSale(): void
    {
        $this->showSuccessModal = false;
        $this->lastInvoice = null;
    }

    protected function handleScan(string $code): bool
    {
        if (! $this->currentShift) {
            $this->scanError = 'Open a shift before selling.';

            return false;
        }

        if ($this->showPaymentModal || $this->showWeighModal) {
            $this->scanError = 'Finish or cancel the payment before scanning.';

            return false;
        }

        // Scanning on the "sale complete" screen starts the next sale.
        if ($this->showSuccessModal) {
            $this->newSale();
        }

        $product = $this->findScannedProduct($code);

        if (! $product || $product->status !== 'active') {
            return false;
        }

        if ($this->stockHere($product->id) <= 0) {
            $this->scanError = "{$product->name} is out of stock in this warehouse.";

            return false;
        }

        $this->addToCart($product->id);

        return true;
    }

    public function render()
    {
        $warehouses = Warehouse::active()->get();

        return view('livewire.pos.pos-terminal', compact('warehouses'))
            ->layout('layouts.app', ['title' => 'POS Terminal']);
    }
}

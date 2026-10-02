<?php

use App\Livewire\Customers\CustomerForm;
use App\Livewire\Membership\RewardForm;
use App\Livewire\Pos\PosTerminal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MembershipReward;
use App\Models\MembershipRewardRedemption;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
    openShift();
});

/** A past purchase that counts towards the member's total spend. */
function pastPurchase(Customer $customer, float $total, string $status = 'paid'): Invoice
{
    return Invoice::create([
        'customer_id' => $customer->id,
        'created_by' => auth()->id(),
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'status' => $status,
        'subtotal' => $total,
        'total' => $total,
        'paid_amount' => $total,
        'invoice_date' => now(),
    ]);
}

function reward(array $attributes): MembershipReward
{
    return MembershipReward::create($attributes + [
        'name' => 'Test reward',
        'max_amount' => null,
        'value' => 0,
        'gift_quantity' => 1,
        'is_active' => true,
    ]);
}

// ============ CUSTOMERS / PHONE ============

it('requires a phone number for every customer', function () {
    Livewire::test(CustomerForm::class)
        ->set('name', 'No Phone')
        ->call('save')
        ->assertHasErrors(['phone' => 'required']);
});

it('rejects a phone number that already belongs to another customer, ignoring formatting', function () {
    Customer::factory()->create(['phone' => '01712345678']);

    Livewire::test(CustomerForm::class)
        ->set('name', 'Duplicate')
        ->set('phone', '01712-345 678')
        ->call('save')
        ->assertHasErrors(['phone' => 'unique']);
});

it('derives the member number from the customer id', function () {
    $customer = Customer::factory()->create();

    expect($customer->member_no)->toBe('MEM-'.str_pad($customer->id, 6, '0', STR_PAD_LEFT));
});

it('registers a new member from the POS and selects them', function () {
    Livewire::test(PosTerminal::class)
        ->set('customerSearch', '01999 888777')
        ->call('openRegisterCustomer')
        ->assertSet('newCustomerPhone', '01999 888777')
        ->set('newCustomerName', 'New Member')
        ->call('registerCustomer')
        ->assertHasNoErrors()
        ->assertSet('showRegisterCustomer', false)
        ->assertSet('customer_id', Customer::where('phone', '01999888777')->value('id'));
});

it('finds a member by phone even when typed with dashes', function () {
    $customer = Customer::factory()->create(['phone' => '01712345678']);

    $results = Livewire::test(PosTerminal::class)
        ->set('customerSearch', '01712-345')
        ->instance()->customerResults;

    expect($results->pluck('id')->all())->toContain($customer->id);
});

// ============ ELIGIBILITY ============

it('offers a single-bill reward only when the bill is inside its range', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 2000, 'max_amount' => 3000, 'reward_type' => 'percent', 'value' => 5]);

    $pos = Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id);                     // 1000: below
    expect($pos->instance()->eligibleRewards->pluck('id')->all())->not->toContain($rule->id);

    $pos->call('incrementQty', 0);                              // 2000: inside
    expect($pos->instance()->eligibleRewards->pluck('id')->all())->toContain($rule->id);

    $pos->call('updateQty', 0, 4);                              // 4000: above
    expect($pos->instance()->eligibleRewards->pluck('id')->all())->not->toContain($rule->id);
});

it('offers a cumulative reward once total spend crosses the target, and only once', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $rule = reward(['basis' => 'cumulative', 'min_amount' => 10000, 'reward_type' => 'fixed', 'value' => 300]);

    pastPurchase($customer, 8500);
    pastPurchase($customer, 5000, 'draft');                     // drafts do not count

    $pos = Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id);                     // 8500 + 1000 = 9500
    expect($pos->instance()->eligibleRewards)->toBeEmpty()
        ->and($pos->instance()->nextReward['remaining'])->toBe(500.0);

    $pos->call('updateQty', 0, 2)                               // 8500 + 2000 = 10500
        ->call('applyReward', $rule->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(MembershipRewardRedemption::where('customer_id', $customer->id)->count())->toBe(1);

    // Next visit: already received, so not offered again.
    $again = Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id);
    expect($again->instance()->eligibleRewards)->toBeEmpty();
});

it('offers no rewards on a walk-in sale', function () {
    $product = Product::factory()->create(['selling_price' => 5000, 'quantity' => 5]);
    reward(['basis' => 'single_invoice', 'min_amount' => 100, 'reward_type' => 'fixed', 'value' => 50]);

    $pos = Livewire::test(PosTerminal::class)->call('addToCart', $product->id);

    expect($pos->instance()->eligibleRewards)->toBeEmpty();
});

// ============ APPLYING ============

it('takes an applied discount off the total and records it with the phone', function () {
    $customer = Customer::factory()->create(['phone' => '01711111111']);
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 1000, 'reward_type' => 'percent', 'value' => 10]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('incrementQty', 0)                               // 2000
        ->call('applyReward', $rule->id)
        ->assertSet('membershipDiscount', 200.0)
        ->call('openPaymentModal')
        ->call('completeSale');

    $invoice = Invoice::latest('id')->first();

    expect((float) $invoice->membership_discount)->toBe(200.0)
        ->and((float) $invoice->total)->toBe(1800.0)
        ->and($invoice->customer_phone)->toBe('01711111111');

    $redemption = MembershipRewardRedemption::first();
    expect($redemption->invoice_id)->toBe($invoice->id)
        ->and($redemption->customer_phone)->toBe('01711111111')
        ->and((float) $redemption->discount_amount)->toBe(200.0);
});

it('lets the cashier change the discount amount', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'fixed', 'value' => 100]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('applyReward', $rule->id)
        ->set("appliedRewards.{$rule->id}.amount", '40')
        ->assertSet('membershipDiscount', 40.0)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect((float) Invoice::latest('id')->first()->total)->toBe(960.0);
});

it('adds a gift as a free line and takes it out of stock', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $gift = Product::factory()->create(['selling_price' => 50, 'quantity' => 10]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'gift',
        'gift_product_id' => $gift->id, 'gift_quantity' => 2]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('applyReward', $rule->id)
        ->assertCount('cart', 2)
        ->assertSet('cart.1.is_gift', true)
        ->assertSet('cart.1.price', 0.0)
        ->call('openPaymentModal')
        ->call('completeSale');

    $invoice = Invoice::latest('id')->first();
    $giftLine = $invoice->items()->where('is_gift', true)->first();

    expect((float) $invoice->total)->toBe(1000.0)
        ->and($giftLine->product_id)->toBe($gift->id)
        ->and($giftLine->quantity)->toBe(2.0)
        ->and((float) $giftLine->subtotal)->toBe(0.0)
        ->and(app(InventoryService::class)->stockIn($gift->id, $this->warehouse->id))->toBe(8.0);
});

it('refuses a gift that is not in stock', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $gift = Product::factory()->create(['quantity' => 1]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'gift',
        'gift_product_id' => $gift->id, 'gift_quantity' => 2]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('applyReward', $rule->id)
        ->assertCount('cart', 1)
        ->assertSet('appliedRewards', []);
});

it('drops an applied reward when the cart falls out of range', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 2000, 'reward_type' => 'fixed', 'value' => 100]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('incrementQty', 0)
        ->call('applyReward', $rule->id)
        ->assertSet('membershipDiscount', 100.0)
        ->call('decrementQty', 0)
        ->assertSet('appliedRewards', [])
        ->assertSet('membershipDiscount', 0.0);
});

it('records nothing when the reward is skipped', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    reward(['basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'fixed', 'value' => 100]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->call('completeSale');

    expect(MembershipRewardRedemption::count())->toBe(0)
        ->and((float) Invoice::latest('id')->first()->membership_discount)->toBe(0.0);
});

// ============ REWARD RULES ============

it('creates a reward rule from the form', function () {
    Livewire::test(RewardForm::class)
        ->set('name', 'Five percent')
        ->set('basis', 'single_invoice')
        ->set('min_amount', '1000')
        ->set('reward_type', 'percent')
        ->set('value', '5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('membership-rewards.index'));

    expect(MembershipReward::first()->summary)->toContain('5% off');
});

it('requires a gift product for a gift reward', function () {
    Livewire::test(RewardForm::class)
        ->set('name', 'Gift')
        ->set('min_amount', '1000')
        ->set('reward_type', 'gift')
        ->call('save')
        ->assertHasErrors(['gift_product_id' => 'required']);
});

// ============ SCREENS ============

it('shows the member panel and earned reward on the POS', function () {
    $customer = Customer::factory()->create(['phone' => '01722222222']);
    $product = Product::factory()->create(['selling_price' => 1000, 'quantity' => 50]);
    reward(['name' => 'Thanks', 'basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'fixed', 'value' => 100]);
    reward(['name' => 'Milestone', 'basis' => 'cumulative', 'min_amount' => 99999, 'reward_type' => 'percent', 'value' => 5]);

    Livewire::test(PosTerminal::class)
        ->call('selectCustomer', $customer->id)
        ->call('addToCart', $product->id)
        ->assertSee($customer->member_no)
        ->assertSee('01722222222')
        ->assertSee('This member earned a reward')
        ->assertSee('more to unlock');
});

it('renders the reward pages and the customer membership section', function () {
    $customer = Customer::factory()->create();
    $rule = reward(['basis' => 'single_invoice', 'min_amount' => 500, 'reward_type' => 'fixed', 'value' => 100]);

    $this->get(route('membership-rewards.index'))->assertOk()->assertSee($rule->name);
    $this->get(route('membership-rewards.create'))->assertOk()->assertSee('Preview');
    $this->get(route('membership-rewards.edit', $rule))->assertOk();
    $this->get(route('customers.edit', $customer))->assertOk()->assertSee($customer->member_no)->assertSee('Rewards received');
});

it('keeps reward pages away from staff', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $this->get(route('membership-rewards.index'))->assertForbidden();
});

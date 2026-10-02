<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MembershipReward;
use App\Models\MembershipRewardRedemption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Member rewards. Every customer is a member; every invoice linked to a
 * customer is a membership purchase.
 *
 * Spend is summed from invoices rather than read from customers.total_purchases,
 * because only the POS keeps that column up to date.
 */
class MembershipService
{
    /** Invoice statuses that do not count towards a member's spend. */
    private const NOT_COUNTED = ['draft', 'cancelled'];

    /** What the member has bought, less anything they brought back. */
    public function customerSpend(int $customerId): float
    {
        return (float) Invoice::sales()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', self::NOT_COUNTED)
            ->sum(DB::raw('total - returned_amount'));
    }

    public function purchaseCount(int $customerId): int
    {
        return Invoice::sales()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', self::NOT_COUNTED)
            ->count();
    }

    /** Ids of the cumulative rules this customer has already received. */
    public function redeemedRewardIds(int $customerId): array
    {
        return MembershipRewardRedemption::where('customer_id', $customerId)
            ->whereNotNull('membership_reward_id')
            ->pluck('membership_reward_id')
            ->unique()
            ->all();
    }

    /**
     * Rules the customer qualifies for on a bill of $billAmount, best first.
     *
     * Cumulative rules count this bill too, so the purchase that crosses the
     * threshold is the one that earns the reward.
     */
    public function eligibleRewards(Customer $customer, float $billAmount, ?float $spend = null): Collection
    {
        if ($billAmount <= 0) {
            return collect();
        }

        $spend ??= $this->customerSpend($customer->id);
        $redeemed = $this->redeemedRewardIds($customer->id);

        return MembershipReward::active()
            ->with('giftProduct')
            ->get()
            ->filter(fn (MembershipReward $r) => $r->isCumulative()
                ? ! in_array($r->id, $redeemed, true) && $spend + $billAmount >= (float) $r->min_amount
                : $billAmount >= (float) $r->min_amount
                    && ($r->max_amount === null || $billAmount <= (float) $r->max_amount))
            ->sortByDesc(fn (MembershipReward $r) => $r->isGift()
                ? (float) ($r->giftProduct?->selling_price ?? 0) * $r->gift_quantity
                : $r->discountFor($billAmount))
            ->values();
    }

    /** The closest cumulative reward not reached yet, for the "x more to unlock" hint. */
    public function nextReward(Customer $customer, float $billAmount = 0, ?float $spend = null): ?array
    {
        $spend ??= $this->customerSpend($customer->id);
        $reached = $spend + $billAmount;

        $reward = MembershipReward::active()
            ->with('giftProduct')
            ->where('basis', 'cumulative')
            ->where('min_amount', '>', $reached)
            ->whereNotIn('id', $this->redeemedRewardIds($customer->id))
            ->orderBy('min_amount')
            ->first();

        return $reward ? [
            'reward' => $reward,
            'remaining' => (float) $reward->min_amount - $reached,
        ] : null;
    }

    public function recordRedemption(
        Invoice $invoice,
        Customer $customer,
        MembershipReward $reward,
        float $discount = 0,
    ): MembershipRewardRedemption {
        return MembershipRewardRedemption::create([
            'customer_id' => $customer->id,
            'membership_reward_id' => $reward->id,
            'invoice_id' => $invoice->id,
            'created_by' => auth()->id(),
            'reward_name' => $reward->name,
            'customer_phone' => $customer->phone,
            'reward_type' => $reward->reward_type,
            'discount_amount' => $reward->isGift() ? 0 : $discount,
            'gift_product_id' => $reward->isGift() ? $reward->gift_product_id : null,
            'gift_quantity' => $reward->isGift() ? $reward->gift_quantity : 0,
        ]);
    }
}

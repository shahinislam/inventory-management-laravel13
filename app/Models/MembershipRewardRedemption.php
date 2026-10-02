<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'customer_id', 'membership_reward_id', 'invoice_id', 'created_by',
    'reward_name', 'customer_phone', 'reward_type',
    'discount_amount', 'gift_product_id', 'gift_quantity',
])]
class MembershipRewardRedemption extends Model
{
    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'gift_quantity' => 'integer',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward()
    {
        return $this->belongsTo(MembershipReward::class, 'membership_reward_id')->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function giftProduct()
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** What the member received, in words. */
    public function getDescriptionAttribute(): string
    {
        return $this->reward_type === 'gift'
            ? ($this->gift_quantity > 1 ? $this->gift_quantity.' × ' : '').($this->giftProduct?->name ?? 'Gift')
            : money($this->discount_amount).' off';
    }
}

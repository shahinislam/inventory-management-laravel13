<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'basis', 'min_amount', 'max_amount',
    'reward_type', 'value', 'gift_product_id', 'gift_quantity',
    'is_active', 'notes',
])]
class MembershipReward extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'value' => 'decimal:2',
            'gift_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function giftProduct()
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    public function redemptions()
    {
        return $this->hasMany(MembershipRewardRedemption::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function isCumulative(): bool
    {
        return $this->basis === 'cumulative';
    }

    public function isGift(): bool
    {
        return $this->reward_type === 'gift';
    }

    /** Discount this rule gives on a bill of $amount; zero for gifts. */
    public function discountFor(float $amount): float
    {
        return match ($this->reward_type) {
            'percent' => round($amount * (float) $this->value / 100, 2),
            'fixed' => min((float) $this->value, $amount),
            default => 0.0,
        };
    }

    /** "5% off", "৳200 off", "2 × Coffee Mug free". */
    public function getRewardLabelAttribute(): string
    {
        return match ($this->reward_type) {
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off',
            'fixed' => money($this->value).' off',
            'gift' => ($this->gift_quantity > 1 ? $this->gift_quantity.' × ' : '')
                .($this->giftProduct?->name ?? 'Gift').' free',
        };
    }

    /** "Total spend reaches ৳10,000" / "Bill between ৳2,000 and ৳5,000". */
    public function getRangeLabelAttribute(): string
    {
        if ($this->isCumulative()) {
            return 'Total spend reaches '.money($this->min_amount);
        }

        return $this->max_amount !== null
            ? 'Bill between '.money($this->min_amount).' and '.money($this->max_amount)
            : 'Bill of '.money($this->min_amount).' or more';
    }

    /** One plain sentence describing the whole rule. */
    public function getSummaryAttribute(): string
    {
        return $this->range_label.' → '.$this->reward_label
            .($this->isCumulative() ? ' (once per customer)' : '');
    }
}

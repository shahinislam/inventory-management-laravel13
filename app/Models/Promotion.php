<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'code', 'description', 'type', 'value',
    'max_discount', 'min_order_amount', 'min_quantity',
    'buy_quantity', 'get_quantity',
    'product_id', 'category_id', 'media_id',
    'usage_limit', 'used_count',
    'starts_at', 'ends_at', 'is_active',
])]
class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'value'            => 'decimal:2',
            'max_discount'     => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'is_active'        => 'boolean',
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
            'usage_limit'      => 'integer',
            'used_count'       => 'integer',
        ];
    }

    public function product()   { return $this->belongsTo(Product::class); }
    public function category()  { return $this->belongsTo(Category::class); }
    public function media()     { return $this->belongsTo(Media::class); }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_promotions');
    }
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_promotions')
                    ->withPivot('used_count', 'last_used_at')
                    ->withTimestamps();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                 ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isActive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at <= now())
            && ($this->ends_at === null || $this->ends_at >= now())
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    public function calculateDiscount(float $amount): float
    {
        $discount = match($this->type) {
            'percentage' => $amount * ($this->value / 100),
            'fixed'      => $this->value,
            default      => 0,
        };
        if ($this->max_discount) $discount = min($discount, $this->max_discount);
        return round($discount, 2);
    }
}

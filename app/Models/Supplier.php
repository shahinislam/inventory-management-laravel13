<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name', 'company_name', 'email', 'phone', 'alternative_phone',
    'address', 'city', 'state', 'country', 'postal_code',
    'tax_number', 'payment_terms', 'credit_limit',
    'current_balance', 'media_id', 'is_active', 'notes',
])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Load each supplier's balance in one query (see getBalanceAttribute).
     * current_balance is not used: it was never written.
     */
    public function scopeWithBalance($q)
    {
        return $q->withSum(
            ['purchaseOrders as balance' => fn ($o) => $o->placed()],
            DB::raw('purchase_orders.total - purchase_orders.returned_amount - purchase_orders.paid_amount'),
        );
    }

    /**
     * What the shop owes this supplier across its placed purchase orders: each
     * order less goods sent back and net payments. Below zero is credit the
     * supplier holds for the shop (paid for goods sent back, not refunded).
     */
    public function getBalanceAttribute($value): float
    {
        if (! array_key_exists('balance', $this->attributes)) {
            $value = $this->purchaseOrders()->placed()
                ->sum(DB::raw('purchase_orders.total - purchase_orders.returned_amount - purchase_orders.paid_amount'));
        }

        return round((float) $value, 2);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->country])
            ->filter()->implode(', ');
    }
}

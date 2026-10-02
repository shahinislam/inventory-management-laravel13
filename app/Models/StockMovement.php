<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'product_id', 'warehouse_id', 'created_by', 'type',
    'quantity', 'before_quantity', 'after_quantity',
    'unit_cost', 'reference_type', 'reference_id',
    'expiry_date', 'batch_number', 'notes',
])]
class StockMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'before_quantity' => 'float',
            'after_quantity' => 'float',
            'unit_cost' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function scopeType($q, string $t)
    {
        return $q->where('type', $t);
    }

    public function scopePurchases($q)
    {
        return $q->where('type', 'purchase');
    }

    public function scopeSales($q)
    {
        return $q->where('type', 'sale');
    }

    public function scopeAdjustments($q)
    {
        return $q->where('type', 'adjustment');
    }

    public function scopeExpiring($q, int $days = 30)
    {
        return $q->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays($days));
    }

    public function isInbound(): bool
    {
        // An adjustment can go either way: read the direction off the stock level.
        if ($this->type === 'adjustment') {
            return $this->after_quantity >= $this->before_quantity;
        }

        return in_array($this->type, ['purchase', 'return', 'transfer_in']);
    }

    public function isOutbound(): bool
    {
        return ! $this->isInbound();
    }
}

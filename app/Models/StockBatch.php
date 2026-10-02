<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Part of a warehouse's stock with a known batch / expiry.
 * Written only by InventoryService.
 */
#[Fillable([
    'product_id', 'warehouse_id', 'batch_number', 'expiry_date',
    'quantity', 'unit_cost', 'received_at',
])]
class StockBatch extends Model
{
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'float',
            'unit_cost' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeExpired($q)
    {
        return $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
    }

    /** Not expired yet, but within $days. */
    public function scopeExpiringWithin($q, int $days)
    {
        return $q->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isBefore(today());
    }

    public function daysLeft(): ?int
    {
        return $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null;
    }
}

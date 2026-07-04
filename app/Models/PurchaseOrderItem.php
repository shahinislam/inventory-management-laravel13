<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'purchase_order_id', 'product_id',
    'quantity', 'received_quantity',
    'unit_cost', 'tax_rate', 'discount',
    'subtotal', 'batch_number', 'expiry_date', 'notes',
])]
class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'received_quantity' => 'integer',
            'unit_cost'         => 'decimal:2',
            'tax_rate'          => 'decimal:2',
            'discount'          => 'decimal:2',
            'subtotal'          => 'decimal:2',
            'expiry_date'       => 'date',
        ];
    }

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function product()       { return $this->belongsTo(Product::class); }

    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity - $this->received_quantity;
    }

    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function calculateSubtotal(): float
    {
        $sub = $this->quantity * $this->unit_cost;
        $sub -= $sub * ($this->discount / 100);
        $sub += $sub * ($this->tax_rate / 100);
        return round($sub, 2);
    }
}

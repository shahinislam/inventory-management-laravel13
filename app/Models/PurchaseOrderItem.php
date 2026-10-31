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
    'unit_label', 'unit_factor', 'parent_item_id',
])]
class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'received_quantity' => 'float',
            'unit_cost' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, round($this->quantity - $this->received_quantity, 3));
    }

    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->quantity - 0.0005;
    }

    public function parentItem()
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    public function returnItems()
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /** Base units (pcs) per ordered unit: 24 when the line was ordered by the carton. */
    public function getFactorAttribute(): float
    {
        return max(1.0, (float) ($this->unit_factor ?: 1));
    }

    /**
     * What one ordered unit really cost: the supplier price plus its share of
     * the order discount, freight and tax, spread by line value. Stock is
     * received at this and sent back to the supplier at this.
     */
    public function landedUnitCost(?PurchaseOrder $order = null): float
    {
        $order ??= $this->purchaseOrder;

        return round((float) $this->unit_cost * $order->landedCostRatio(), 4);
    }

    /** The landed cost per base unit (per piece when ordered by the carton). */
    public function landedBaseUnitCost(?PurchaseOrder $order = null): float
    {
        $order ??= $this->purchaseOrder;

        return round((float) $this->unit_cost * $order->landedCostRatio() / $this->factor, 4);
    }

    public function calculateSubtotal(): float
    {
        $sub = $this->quantity * $this->unit_cost;
        $sub -= $sub * ($this->discount / 100);
        $sub += $sub * ($this->tax_rate / 100);

        return round($sub, 2);
    }
}

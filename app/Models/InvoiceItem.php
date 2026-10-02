<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice_id', 'product_id',
    'product_name', 'product_sku',
    'quantity', 'unit_price',
    'tax_rate', 'discount', 'subtotal', 'is_gift', 'notes',
    'unit_cost', 'parent_item_id', 'restock',
])]
class InvoiceItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'is_gift' => 'boolean',
            'unit_cost' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    /** On a return invoice: the sold line this one gives back. */
    public function parentItem()
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /** Return lines raised against this sold line. */
    public function returnItems()
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    /** How much of this sold line can still come back. */
    public function returnableQuantity(): float
    {
        return max(0, round($this->quantity - (float) $this->returnItems()->sum('quantity'), 3));
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateSubtotal(): float
    {
        $sub = $this->quantity * $this->unit_price;
        $sub -= $sub * ($this->discount / 100);
        $sub += $sub * ($this->tax_rate / 100);

        return round($sub, 2);
    }
}

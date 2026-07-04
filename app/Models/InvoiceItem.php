<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'invoice_id', 'product_id',
    'product_name', 'product_sku',
    'quantity', 'unit_price',
    'tax_rate', 'discount', 'subtotal', 'notes',
])]
class InvoiceItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'tax_rate'   => 'decimal:2',
            'discount'   => 'decimal:2',
            'subtotal'   => 'decimal:2',
        ];
    }

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function product() { return $this->belongsTo(Product::class); }

    public function calculateSubtotal(): float
    {
        $sub = $this->quantity * $this->unit_price;
        $sub -= $sub * ($this->discount / 100);
        $sub += $sub * ($this->tax_rate / 100);
        return round($sub, 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'media_id',
    'cost_price', 'selling_price', 'tax_rate', 'discount',
    'quantity', 'min_stock_level', 'unit',
    'weight', 'dimensions', 'discount_type', 'status',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'cost_price'      => 'decimal:2',
            'selling_price'   => 'decimal:2',
            'tax_rate'        => 'decimal:2',
            'discount'        => 'decimal:2',
            'weight'          => 'decimal:2',
            'quantity'        => 'integer',
            'min_stock_level' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            $m->slug ??= Str::slug($m->name);
            $m->sku  ??= strtoupper(Str::random(8));
        });
    }

    public function category()          { return $this->belongsTo(Category::class); }
    public function supplier()          { return $this->belongsTo(Supplier::class); }
    public function media()             { return $this->belongsTo(Media::class); }
    public function stockMovements()    { return $this->hasMany(StockMovement::class); }
    public function purchaseOrderItems(){ return $this->hasMany(PurchaseOrderItem::class); }
    public function invoiceItems()      { return $this->hasMany(InvoiceItem::class); }
    public function promotions()        { return $this->belongsToMany(Promotion::class, 'product_promotions'); }

    public function scopeActive($q)   { return $q->where('status', 'active'); }
    public function scopeLowStock($q) { return $q->whereColumn('quantity', '<=', 'min_stock_level'); }
    public function scopeInStock($q)  { return $q->where('quantity', '>', 0); }

    public function isLowStock(): bool   { return $this->quantity <= $this->min_stock_level; }
    public function isOutOfStock(): bool { return $this->quantity === 0; }

    public function getPriceAfterDiscountAttribute(): float
    {
        return $this->selling_price - ($this->selling_price * $this->discount / 100);
    }

    public function getPriceWithTaxAttribute(): float
    {
        return $this->price_after_discount + ($this->price_after_discount * $this->tax_rate / 100);
    }
}

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
    'purchase_unit', 'purchase_unit_factor', 'track_expiry',
    'weight', 'dimensions', 'discount_type', 'status',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    /** Units sold by weight or volume, in any fraction. */
    public const LOOSE_UNITS = ['kg', 'g', 'ltr', 'ml'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'weight' => 'decimal:2',
            // Floats, not decimal:N — that cast returns strings, which break comparisons.
            'quantity' => 'float',
            'min_stock_level' => 'float',
            'purchase_unit_factor' => 'float',
            'track_expiry' => 'boolean',
        ];
    }

    /** Sold by weight/volume (rice by kg), so quantities may be fractional. */
    public function isLoose(): bool
    {
        return static::isLooseUnit($this->unit);
    }

    public static function isLooseUnit(?string $unit): bool
    {
        return in_array(strtolower((string) $unit), self::LOOSE_UNITS, true);
    }

    /** Validation for a quantity of this product: fractions only for loose units. */
    public function quantityRule(float $min = 0.001): string
    {
        return $this->isLoose() ? "numeric|min:{$min}" : 'numeric|integer|min:'.max(1, (int) ceil($min));
    }

    public function batches()
    {
        return $this->hasMany(StockBatch::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            $m->slug ??= Str::slug($m->name);
            $m->sku ??= strtoupper(Str::random(8));
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'product_promotions');
    }

    /**
     * Per-warehouse stock levels.
     *
     * $product->warehouses->first()->pivot->quantity is how much sits in that
     * warehouse. The total across all of them is cached on products.quantity.
     */
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'product_warehouse')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * How much of this product is in one specific warehouse.
     */
    public function stockIn(?int $warehouseId): float
    {
        if (! $warehouseId) {
            return 0.0;
        }

        return round((float) $this->warehouses()
            ->where('warehouses.id', $warehouseId)
            ->first()?->pivot->quantity, 3);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeLowStock($q)
    {
        return $q->whereColumn('quantity', '<=', 'min_stock_level');
    }

    public function scopeInStock($q)
    {
        return $q->where('quantity', '>', 0);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_stock_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function getPriceAfterDiscountAttribute(): float
    {
        return $this->selling_price - ($this->selling_price * $this->discount / 100);
    }

    public function getPriceWithTaxAttribute(): float
    {
        return $this->price_after_discount + ($this->price_after_discount * $this->tax_rate / 100);
    }
}

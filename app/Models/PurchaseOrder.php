<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'order_number', 'supplier_id', 'warehouse_id',
    'created_by', 'approved_by', 'status',
    'subtotal', 'tax', 'discount', 'courier_charge', 'courier_cost', 'total', 'paid_amount',
    'order_date', 'expected_date', 'payment_due_date', 'received_date', 'notes',
    'type', 'parent_order_id', 'returned_amount',
])]
class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'courier_charge' => 'decimal:2',
            'courier_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'order_date' => 'date',
            'expected_date' => 'date',
            'payment_due_date' => 'date',
            'received_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->order_number ??= NumberGeneratorService::generate(
            $m->type === 'return' ? 'purchase_return' : 'purchase'
        ));
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    // ============ KINDS ============
    // Returns to the supplier are stored here too (type=return, parent_order_id).
    // Anything that means "purchases" must use scopePurchases().

    public function scopePurchases($q)
    {
        return $q->where('purchase_orders.type', 'purchase');
    }

    public function scopeReturns($q)
    {
        return $q->where('purchase_orders.type', 'return');
    }

    public function isReturn(): bool
    {
        return $this->type === 'return';
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function returns()
    {
        return $this->hasMany(self::class, 'parent_order_id')->where('type', 'return');
    }

    /** Purchases placed with the supplier: the ones that are owed for. */
    public function scopePlaced($q)
    {
        return $q->purchases()->whereNotIn('purchase_orders.status', ['draft', 'cancelled']);
    }

    /** Placed orders that still owe the supplier money. */
    public function scopeWithDue($q)
    {
        return $q->placed()->whereRaw('paid_amount + returned_amount < total');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopeReceived($q)
    {
        return $q->where('status', 'received');
    }

    /**
     * Owed to the supplier: the order less goods sent back and payments made.
     * paid_amount is net: a refund the supplier gives on a return comes off it.
     */
    public function getDueAmountAttribute(): float
    {
        return max(0, round((float) $this->total - (float) $this->returned_amount - (float) $this->paid_amount, 2));
    }

    /**
     * Owed to the shop: paid for goods that were then sent back and not
     * refunded. It stays with the supplier as credit.
     */
    public function getCreditAmountAttribute(): float
    {
        return max(0, round((float) $this->returned_amount + (float) $this->paid_amount - (float) $this->total, 2));
    }

    /**
     * The most the supplier can refund on a return worth $value: a return
     * first comes off what is still owed, so only money already paid beyond
     * the goods kept can come back (the credit the order would then hold).
     */
    public function refundableFor(float $value): float
    {
        return max(0, round((float) $this->paid_amount + (float) $this->returned_amount + $value - (float) $this->total, 2));
    }

    /**
     * What the supplier's bill is per unit of line value: everything paid to the
     * supplier for the goods (order discount, freight billed on the order, order
     * tax) over the lines' value. A line's landed cost is its value × this.
     * Freight paid separately to a courier (courier_cost) is not on the bill.
     */
    public function landedCostRatio(): float
    {
        $subtotal = (float) $this->subtotal;

        return $subtotal > 0 ? (float) $this->total / $subtotal : 1.0;
    }

    public function isPaid(): bool
    {
        return $this->due_amount <= 0;
    }

    /** Whether any stock has arrived against this order. */
    public function hasReceivedStock(): bool
    {
        return $this->items()->where('received_quantity', '>', 0)->exists();
    }

    /**
     * An order can be cancelled until anything arrives against it: once stock
     * is in, the goods are owed for and go back only through a return.
     */
    public function canCancel(): bool
    {
        return ! $this->isReturn()
            && ! in_array($this->status, ['received', 'cancelled'], true)
            && ! $this->hasReceivedStock()
            // A payment already made would drop out of what the supplier owes back.
            && (float) $this->paid_amount <= 0;
    }

    /** Payments can be made once the order is placed, until it is fully paid. */
    public function canRecordPayment(): bool
    {
        return ! in_array($this->status, ['draft', 'cancelled'], true) && ! $this->isPaid();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Whether this order can still be placed with the supplier.
     *
     * `pending` and `approved` are legacy states from when purchase orders
     * required approval; they are kept so existing orders can still move on.
     */
    public function canPlace(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'approved'], true);
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['pending', 'approved', 'ordered'], true);
    }
}

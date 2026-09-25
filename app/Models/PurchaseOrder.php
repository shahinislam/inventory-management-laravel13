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
            'order_date' => 'date',
            'expected_date' => 'date',
            'payment_due_date' => 'date',
            'received_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->order_number ??= NumberGeneratorService::generate('purchase'));
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

    /** Placed orders that still owe the supplier money. */
    public function scopeWithDue($q)
    {
        return $q->whereNotIn('status', ['draft', 'cancelled'])
            ->whereColumn('paid_amount', '<', 'total');
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

    public function getDueAmountAttribute(): float
    {
        return $this->total - $this->paid_amount;
    }

    public function isPaid(): bool
    {
        return $this->paid_amount >= $this->total;
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

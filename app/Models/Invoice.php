<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'invoice_number', 'warehouse_id', 'customer_id', 'created_by',
    'customer_name', 'customer_email', 'customer_phone', 'customer_address',
    'status', 'payment_method',
    'subtotal', 'tax', 'discount', 'membership_discount', 'courier_charge', 'courier_cost', 'total', 'paid_amount', 'due_amount',
    'invoice_date', 'due_date', 'paid_date', 'notes',
    'type', 'parent_invoice_id', 'is_held', 'held_label', 'shift_id', 'returned_amount',
])]
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_held' => 'boolean',
            'returned_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'membership_discount' => 'decimal:2',
            'courier_charge' => 'decimal:2',
            'courier_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            // A held sale is parked, not issued: it must not use up an invoice number.
            $m->invoice_number ??= match (true) {
                (bool) $m->is_held => 'HOLD-'.strtoupper(uniqid()),
                $m->type === 'return' => NumberGeneratorService::generate('sales_return'),
                default => NumberGeneratorService::generate('invoice'),
            };
        });
    }

    // ============ KINDS ============
    // One table holds sales, return notes (type=return) and parked POS sales
    // (is_held). Anything that means "sales" must use scopeSales().

    /** Issued sales only: no return notes, no parked sales. */
    public function scopeSales($q)
    {
        return $q->where('invoices.type', 'sale')->where('invoices.is_held', false);
    }

    public function scopeReturns($q)
    {
        return $q->where('invoices.type', 'return');
    }

    public function scopeHeld($q)
    {
        return $q->where('invoices.is_held', true);
    }

    public function isReturn(): bool
    {
        return $this->type === 'return';
    }

    /** What the customer actually bought, after anything brought back. */
    public function getNetTotalAttribute(): float
    {
        return round((float) $this->total - (float) $this->returned_amount, 2);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    public function returns()
    {
        return $this->hasMany(self::class, 'parent_invoice_id')->where('type', 'return');
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(MembershipRewardRedemption::class);
    }

    /** Sold to a registered customer (every customer is a member). */
    public function isMembership(): bool
    {
        return $this->customer_id !== null;
    }

    /** Same format as Customer::member_no, without loading the customer. */
    public function getMemberNoAttribute(): ?string
    {
        return $this->customer_id ? 'MEM-'.str_pad((string) $this->customer_id, 6, '0', STR_PAD_LEFT) : null;
    }

    public function scopePaid($q)
    {
        return $q->where('status', 'paid');
    }

    public function scopeOverdue($q)
    {
        return $q->where('status', 'overdue');
    }

    public function scopeUnpaid($q)
    {
        return $q->whereIn('status', ['draft', 'sent', 'partial', 'overdue']);
    }

    /** Issued invoices the customer still owes money on (drafts are not owed yet). */
    public function scopeWithDue($q)
    {
        return $q->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('due_amount', '>', 0);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('invoice_date', today());
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function calculateDue(): float
    {
        return max(0, round((float) $this->total - (float) $this->returned_amount - (float) $this->paid_amount, 2));
    }

    /**
     * What the shop made (or lost) on delivery for this sale.
     *
     * Zero when the charge is passed straight through to the courier, negative
     * when the shop absorbed all or part of the delivery cost.
     */
    public function getCourierMarginAttribute(): float
    {
        return (float) $this->courier_charge - (float) $this->courier_cost;
    }
}

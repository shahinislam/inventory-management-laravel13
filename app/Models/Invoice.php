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
    'subtotal', 'tax', 'discount', 'courier_charge', 'courier_cost', 'total', 'paid_amount', 'due_amount',
    'invoice_date', 'due_date', 'paid_date', 'notes',
])]
class Invoice extends Model
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
            'due_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->invoice_number ??= NumberGeneratorService::generate('invoice'));
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
        return max(0, $this->total - $this->paid_amount);
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

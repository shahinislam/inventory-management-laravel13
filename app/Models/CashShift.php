<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'shift_number', 'user_id', 'warehouse_id', 'status',
    'opened_at', 'opening_cash', 'closed_at', 'closed_by',
    'expected_cash', 'counted_cash', 'difference', 'notes',
])]
class CashShift extends Model
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->shift_number ??= NumberGeneratorService::generate('shift'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'shift_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'shift_id');
    }

    /** Drawer cash in/out and expenses paid from the till. */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'shift_id');
    }

    public function scopeOpen($q)
    {
        return $q->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}

<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money spent outside sales and purchases (type 'expense'), or cash moved in /
 * out of a till during a shift ('cash_in' / 'cash_out'). Only 'expense' is a
 * cost in the profit & loss.
 */
#[Fillable([
    'expense_number', 'type', 'category', 'amount', 'expense_date',
    'method', 'payment_account_id', 'shift_id', 'paid_to',
    'reference', 'media_id', 'notes', 'created_by',
])]
class Expense extends Model
{
    use SoftDeletes;

    /** Offered as suggestions; any category text is accepted. */
    public const CATEGORIES = [
        'Rent', 'Salary', 'Electricity', 'Water', 'Gas', 'Internet & Phone',
        'Transport', 'Packaging', 'Repair & Maintenance', 'Cleaning',
        'Marketing', 'Bank Charges', 'Tax & Fees', 'Other',
    ];

    public const TYPES = [
        'expense' => 'Expense',
        'cash_in' => 'Cash added to drawer',
        'cash_out' => 'Cash taken from drawer',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->expense_number ??= NumberGeneratorService::generate('expense'));
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class)->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Real costs only — not drawer cash movements. */
    public function scopeCosts($q)
    {
        return $q->where('type', 'expense');
    }
}

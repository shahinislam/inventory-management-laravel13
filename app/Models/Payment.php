<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'payment_number', 'invoice_id', 'created_by',
    'payment_account_id', 'amount', 'method', 'status',
    'reference', 'bank_name', 'account_number',
    'cheque_number', 'payment_date', 'notes', 'shift_id',
])]
class Payment extends Model
{
    use HasFactory;

    /** Every payment method, with the label staff see. Shared by all money screens. */
    public const METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile_banking' => 'bKash / Nagad',
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    public static function methodLabel(?string $method): string
    {
        return $method === 'split' ? 'Split' : (self::METHODS[$method] ?? ucfirst(str_replace('_', ' ', (string) $method)));
    }

    /** bKash / Nagad payments are traced by the wallet's transaction ID. */
    public static function needsReference(?string $method): bool
    {
        return $method === 'mobile_banking';
    }

    public static function methodRule(): string
    {
        return 'required|in:'.implode(',', array_keys(self::METHODS));
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'shift_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            $m->payment_number ??= NumberGeneratorService::generate('payment');

            // Snapshot the account details so the payment stays readable even
            // if the account is later renamed or deleted.
            if ($m->payment_account_id && $account = PaymentAccount::find($m->payment_account_id)) {
                $m->bank_name ??= $account->bank_name ?? $account->name;
                $m->account_number ??= $account->account_number;
            }
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeRefunded($q)
    {
        return $q->where('status', 'refunded');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}

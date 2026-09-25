<?php

namespace App\Models;

use App\Services\NumberGeneratorService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'payment_number', 'purchase_order_id', 'created_by',
    'payment_account_id', 'amount', 'method',
    'reference', 'payment_date', 'notes',
])]
class PurchasePayment extends Model
{
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
        static::creating(fn ($m) => $m->payment_number ??= NumberGeneratorService::generate('purchase_payment'));
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

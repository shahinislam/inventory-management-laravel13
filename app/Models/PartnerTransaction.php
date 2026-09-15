<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'partner_id', 'created_by', 'type', 'amount',
    'transaction_date', 'reference', 'notes',
])]
class PartnerTransaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeInvestments($q)
    {
        return $q->where('type', 'investment');
    }

    public function scopeWithdrawals($q)
    {
        return $q->where('type', 'withdrawal');
    }

    public function isInvestment(): bool
    {
        return $this->type === 'investment';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'period_start', 'period_end',
    'total_sales', 'total_purchases', 'courier_margin', 'profit',
    'closed_by', 'closed_at', 'notes',
])]
class PartnerPeriod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_sales' => 'decimal:2',
            'total_purchases' => 'decimal:2',
            'courier_margin' => 'decimal:2',
            'profit' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function shares()
    {
        return $this->hasMany(PartnerPeriodShare::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

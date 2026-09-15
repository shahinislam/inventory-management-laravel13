<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'partner_period_id', 'partner_id', 'share_percentage', 'profit_share',
])]
class PartnerPeriodShare extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'share_percentage' => 'decimal:2',
            'profit_share' => 'decimal:2',
        ];
    }

    public function period()
    {
        return $this->belongsTo(PartnerPeriod::class, 'partner_period_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}

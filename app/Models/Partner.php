<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'name', 'email', 'phone',
    'share_percentage', 'is_active', 'notes',
])]
class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'share_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(PartnerTransaction::class);
    }

    public function periodShares()
    {
        return $this->hasMany(PartnerPeriodShare::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Total share held by active partners, optionally ignoring one of them.
     *
     * The exclusion is for validation while editing: the partner being saved
     * must be measured with its new share, not the one already stored.
     */
    public static function totalActiveShare(?int $exceptId = null): float
    {
        return (float) static::active()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum('share_percentage');
    }

    public function totalInvested(): float
    {
        return (float) $this->transactions()->where('type', 'investment')->sum('amount');
    }

    public function totalWithdrawn(): float
    {
        return (float) $this->transactions()->where('type', 'withdrawal')->sum('amount');
    }
}

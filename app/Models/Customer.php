<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name', 'email', 'phone', 'alternative_phone',
    'date_of_birth', 'gender', 'address', 'city',
    'state', 'country', 'postal_code', 'tax_number',
    'credit_limit', 'current_balance',
    'total_purchases', 'total_orders',
    'media_id', 'is_active', 'notes',
])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'total_purchases' => 'decimal:2',
            'total_orders' => 'integer',
        ];
    }

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'customer_promotions')
            ->withPivot('used_count', 'last_used_at')
            ->withTimestamps();
    }

    /**
     * Strip spaces, dashes, dots and brackets so "(555) 010-0123" and
     * "5550100123" are the same member. A leading + is kept.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[\s\-\.\(\)]/', '', (string) $phone);

        return $phone === '' ? null : $phone;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = static::normalizePhone($value);
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(MembershipRewardRedemption::class);
    }

    /** Every customer is a member; the number is derived from the id. */
    public function getMemberNoAttribute(): string
    {
        return 'MEM-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->country])
            ->filter()->implode(', ');
    }

    /**
     * Orders, spend and balance worked out from the invoices, so every screen
     * agrees with the Due report. Adds orders_count, spent and balance_due.
     */
    public function scopeWithMoney($q)
    {
        $issued = fn ($i) => $i->sales()->whereNotIn('invoices.status', ['draft', 'cancelled']);

        return $q->withCount(['invoices as orders_count' => $issued])
            ->withSum(['invoices as spent' => $issued], DB::raw('total - returned_amount'))
            ->withSum(['invoices as balance_due' => fn ($i) => $i->sales()->withDue()], 'due_amount');
    }

    /** What the customer still owes across their sales. */
    public function owed(): float
    {
        return round((float) $this->invoices()->sales()->withDue()->sum('due_amount'), 2);
    }

    /** Room left under the credit limit, or null when the customer has no limit. */
    public function creditLeft(): ?float
    {
        return (float) $this->credit_limit > 0 ? round((float) $this->credit_limit - $this->owed(), 2) : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A bank account, card or mobile wallet that money moves through.
 *
 * Card and bank payments are tagged with one of these so money in and out
 * can be traced per account. Cash, cheque and "other" payments are not.
 */
#[Fillable([
    'name', 'type', 'bank_name', 'account_number',
    'holder_name', 'is_active', 'notes',
])]
class PaymentAccount extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'bank' => 'Bank Account',
        'card' => 'Card',
        'mobile_wallet' => 'Mobile Wallet',
    ];

    /** Which account types each payment method may be paid through. */
    private const METHOD_TYPES = [
        'card' => ['card'],
        'bank_transfer' => ['bank'],
        'mobile_banking' => ['mobile_wallet'],
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function purchasePayments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Active accounts that can take a payment made by this method. */
    public function scopeForMethod($q, string $method)
    {
        return $q->active()->whereIn('type', self::METHOD_TYPES[$method] ?? [])->orderBy('name');
    }

    public static function requiredFor(string $method): bool
    {
        return isset(self::METHOD_TYPES[$method]);
    }

    /**
     * Validation rule for an account id paid by $method: required for card and
     * bank payments, and it must be an active account of a matching type.
     */
    public static function rule(string $method): array
    {
        if (! self::requiredFor($method)) {
            return ['nullable'];
        }

        return ['required', self::existsRule($method)];
    }

    private static function existsRule(string $method): Exists
    {
        return Rule::exists('payment_accounts', 'id')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('type', self::METHOD_TYPES[$method]);
    }

    /** e.g. "City Bank Current ••1234" */
    public function getDisplayNameAttribute(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->account_number);

        return $digits !== ''
            ? $this->name.' ••'.substr($digits, -4)
            : $this->name;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}

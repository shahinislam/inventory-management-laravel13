<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'code', 'address', 'city', 'state',
    'country', 'postal_code', 'phone', 'email',
    'manager_id', 'capacity', 'is_default', 'is_active', 'notes',
])]
class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'capacity'   => 'integer',
        ];
    }

    public function manager()        { return $this->belongsTo(User::class, 'manager_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
    public function invoices()       { return $this->hasMany(Invoice::class); }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeDefault($q) { return $q->where('is_default', true); }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }
}

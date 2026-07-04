<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'description',
    'parent_id', 'media_id', 'order', 'is_active',
])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order'     => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->slug ??= Str::slug($m->name));
        static::updating(fn($m) => $m->slug ??= Str::slug($m->name));
    }

    public function parent()    { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children()  { return $this->hasMany(Category::class, 'parent_id'); }
    public function media()     { return $this->belongsTo(Media::class); }
    public function products()  { return $this->hasMany(Product::class); }
    public function promotions(){ return $this->hasMany(Promotion::class); }

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeRoots($q)   { return $q->whereNull('parent_id'); }
    public function scopeOrdered($q) { return $q->orderBy('order'); }
}

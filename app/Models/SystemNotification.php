<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'type', 'title', 'message',
    'model_type', 'model_id', 'action_url',
    'is_read', 'read_at',
])]
class SystemNotification extends Model
{
    use HasFactory;

    protected $table = 'system_notifications';

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($q)
    {
        return $q->where('is_read', false);
    }

    public function scopeRead($q)
    {
        return $q->where('is_read', true);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }

    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $extra = []
    ): self {
        return static::create(array_merge([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ], $extra));
    }
}

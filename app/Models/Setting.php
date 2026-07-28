<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'group', 'type', 'is_public'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_cached()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::flushCache();
    }

    public static function getGroup(string $group): array
    {
        return Cache::remember("settings:group:{$group}", 3600, function () use ($group) {
            return static::where('group', $group)
                ->get()
                ->mapWithKeys(fn ($s) => [$s->key => static::castValue($s->value, $s->type)])
                ->toArray();
        });
    }

    /**
     * Load every setting in a single query, cached as one entry.
     */
    private static function all_cached(): array
    {
        return Cache::remember('settings:all', 3600, function () {
            return static::all()
                ->mapWithKeys(fn ($s) => [$s->key => static::castValue($s->value, $s->type)])
                ->toArray();
        });
    }

    /**
     * Forget the aggregate cache and every per-group cache.
     */
    public static function flushCache(): void
    {
        Cache::forget('settings:all');

        static::query()->distinct()->pluck('group')
            ->each(fn ($group) => Cache::forget("settings:group:{$group}"));
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (float) $value : $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}

<?php

namespace App\Models;

use App\Support\ThemeColors;
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

    /**
     * A switch setting. A row created by set() is typed 'text', so its value
     * comes back as the string "false" — truthy — unless parsed like this.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        // Derive the group from the key prefix when creating a row. Without this
        // a brand-new key falls back to the column default ('general'), so
        // getGroup() would never return it.
        $prefix = str_contains($key, '.') ? strstr($key, '.', true) : null;
        $group = in_array($prefix, self::GROUPS, true) ? $prefix : 'general';

        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;

        if (! $setting->exists) {
            $setting->group = $group;
            $setting->type ??= 'text';
        }

        $setting->save();

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
    /**
     * Every group cached by getGroup(). Enumerated as a constant rather than
     * discovered with a distinct() query: a group whose rows were all just
     * deleted — or that was cached as empty before its rows existed — would not
     * appear in that query, so its stale entry survived the flush.
     */
    private const GROUPS = [
        'general', 'company', 'invoice', 'pos',
        'notification', 'currency', 'tax', 'email', 'theme', 'sms',
    ];

    public static function flushCache(): void
    {
        Cache::forget('settings:all');

        foreach (self::GROUPS as $group) {
            Cache::forget("settings:group:{$group}");
        }

        // Also clear any group present in the table but not listed above, so a
        // future group added to the enum is still invalidated.
        static::query()->distinct()->pluck('group')
            ->reject(fn ($group) => in_array($group, self::GROUPS, true))
            ->each(fn ($group) => Cache::forget("settings:group:{$group}"));

        // The derived brand palette is built from the theme group, so it has to
        // go too or the UI keeps the old colours until the cache expires.
        ThemeColors::flushCache();
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

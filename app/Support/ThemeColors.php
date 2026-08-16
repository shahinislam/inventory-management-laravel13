<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Turns the three brand colours stored in settings into CSS custom properties.
 *
 * A single hex per role is expanded into a full 50–950 ramp so utilities like
 * bg-primary-600 or text-secondary-400 behave exactly like Tailwind's built-in
 * palettes. The ramp is generated in OKLCH: lightness is interpolated across the
 * standard Tailwind steps while the hue is preserved and chroma is tapered at
 * the extremes, which keeps pale and very dark steps from looking muddy the way
 * naive RGB lightening does.
 */
class ThemeColors
{
    /**
     * Lightness targets per step, matching the perceptual spacing Tailwind v4
     * uses for its own palettes.
     */
    private const LIGHTNESS = [
        50 => 0.971,
        100 => 0.936,
        200 => 0.885,
        300 => 0.809,
        400 => 0.707,
        500 => 0.637,
        600 => 0.577,
        700 => 0.505,
        800 => 0.444,
        900 => 0.396,
        950 => 0.284,
    ];

    /**
     * Chroma multiplier per step. Mid tones carry full saturation; the ends are
     * damped because very light and very dark colours cannot hold high chroma
     * without drifting out of gamut.
     */
    private const CHROMA_SCALE = [
        50 => 0.10,
        100 => 0.20,
        200 => 0.38,
        300 => 0.62,
        400 => 0.88,
        500 => 1.00,
        600 => 1.00,
        700 => 0.92,
        800 => 0.80,
        900 => 0.68,
        950 => 0.48,
    ];

    /** Fallbacks used when a setting is missing or malformed. */
    private const DEFAULTS = [
        'primary' => '#4f46e5',
        'secondary' => '#0d9488',
        'tertiary' => '#d97706',
    ];

    /**
     * Build the <style> payload injected into the document head.
     *
     * Cached because it runs on every request and the inputs change rarely;
     * Setting::flushCache() clears this alongside the settings themselves.
     */
    public static function css(): string
    {
        return Cache::remember('theme:css', 3600, function () {
            $theme = Setting::getGroup('theme');
            $lines = [];

            foreach (self::DEFAULTS as $role => $fallback) {
                $hex = self::normalizeHex($theme["theme.{$role}"] ?? null) ?? $fallback;

                foreach (self::ramp($hex) as $step => $oklch) {
                    $lines[] = "--color-{$role}-{$step}: {$oklch};";
                }

                // Bare alias (e.g. --color-primary) for the 600 step, so
                // bg-primary works without a shade suffix.
                $lines[] = "--color-{$role}: var(--color-{$role}-600);";
            }

            return ':root{'.implode('', $lines).'}';
        });
    }

    /**
     * Expand one hex colour into the full step => oklch(...) ramp.
     *
     * @return array<int, string>
     */
    public static function ramp(string $hex): array
    {
        [$l, $c, $h] = self::hexToOklch($hex);

        // Guard against a grey input: with no chroma there is no hue to
        // preserve, so the ramp stays neutral rather than inventing a tint.
        $baseChroma = $c;

        $ramp = [];

        foreach (self::LIGHTNESS as $step => $lightness) {
            $chroma = round($baseChroma * self::CHROMA_SCALE[$step], 4);

            $ramp[$step] = sprintf(
                'oklch(%s%% %s %s)',
                round($lightness * 100, 1),
                $chroma,
                round($h, 3)
            );
        }

        return $ramp;
    }

    /**
     * Accepts #rgb, #rrggbb, or the same without the hash. Returns null when the
     * value cannot be parsed, so the caller can fall back to a default rather
     * than emitting broken CSS.
     */
    public static function normalizeHex(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $hex = ltrim(trim($value), '#');

        if (strlen($hex) === 3 && ctype_xdigit($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return strlen($hex) === 6 && ctype_xdigit($hex) ? '#'.strtolower($hex) : null;
    }

    /**
     * sRGB hex -> OKLCH. Follows Björn Ottosson's OKLab definition: gamma
     * expand to linear sRGB, convert to LMS, apply the cube root, then the
     * OKLab matrix, and finally express a/b as chroma + hue.
     *
     * @return array{0: float, 1: float, 2: float} [lightness 0-1, chroma, hue degrees]
     */
    private static function hexToOklch(string $hex): array
    {
        $hex = ltrim($hex, '#');

        $toLinear = function (float $channel): float {
            $channel /= 255;

            return $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        };

        $r = $toLinear((float) hexdec(substr($hex, 0, 2)));
        $g = $toLinear((float) hexdec(substr($hex, 2, 2)));
        $b = $toLinear((float) hexdec(substr($hex, 4, 2)));

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $labL = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $labA = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $labB = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $chroma = sqrt($labA ** 2 + $labB ** 2);
        $hue = rad2deg(atan2($labB, $labA));

        if ($hue < 0) {
            $hue += 360;
        }

        return [$labL, $chroma, $hue];
    }

    public static function flushCache(): void
    {
        Cache::forget('theme:css');
    }
}

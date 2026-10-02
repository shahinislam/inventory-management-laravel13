<?php

namespace App\Services\Barcode;

/**
 * Dependency-free Code 128 encoder (subsets B and C) that renders an inline SVG,
 * plus helpers for in-store EAN-13 numbers.
 */
class Code128
{
    /** Bar/space module widths for symbol values 0–106 (106 = stop). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const START_C = 105;

    private const CODE_B = 100;

    private const CODE_C = 99;

    private const STOP = 106;

    private const QUIET_ZONE = 10;

    /**
     * Symbol values (start, data, checksum, stop) for the given data.
     *
     * @return list<int>
     */
    public static function encode(string $data): array
    {
        $length = strlen($data);
        $values = [];
        $set = null;
        $i = 0;

        while ($i < $length) {
            $run = strspn($data, '0123456789', $i);
            $useC = $run >= 4 && ($run >= 6 || $i === 0 || $i + $run === $length);

            if ($useC) {
                $pairs = intdiv($run, 2);

                if ($set !== 'C') {
                    $values[] = $set === null ? self::START_C : self::CODE_C;
                    $set = 'C';
                }

                for ($p = 0; $p < $pairs; $p++, $i += 2) {
                    $values[] = (int) substr($data, $i, 2);
                }

                continue;
            }

            if ($set !== 'B') {
                $values[] = $set === null ? self::START_B : self::CODE_B;
                $set = 'B';
            }

            $ord = ord($data[$i]);
            $values[] = ($ord >= 32 && $ord <= 126 ? $ord : ord('?')) - 32;
            $i++;
        }

        if ($values === []) {
            $values[] = self::START_B;
        }

        $checksum = $values[0];
        foreach (array_slice($values, 1) as $position => $value) {
            $checksum += $value * ($position + 1);
        }

        $values[] = $checksum % 103;
        $values[] = self::STOP;

        return $values;
    }

    /** Inline SVG barcode. Each bar is one <rect>; quiet zones of 10 modules on both sides. */
    public static function svg(string $data, float $height = 40, float $moduleWidth = 1.5, bool $withText = false): string
    {
        $x = self::QUIET_ZONE;
        $bars = [];

        foreach (self::encode($data) as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $index => $width) {
                if ($index % 2 === 0) {
                    $bars[] = [$x, (int) $width];
                }
                $x += (int) $width;
            }
        }

        $modules = $x + self::QUIET_ZONE;
        $width = round($modules * $moduleWidth, 2);
        $textHeight = $withText ? max(8, $height * 0.28) : 0;
        $totalHeight = round($height + $textHeight, 2);

        $rects = '';
        foreach ($bars as [$bx, $bw]) {
            $rects .= sprintf('<rect x="%s" y="0" width="%s" height="%s"/>',
                round($bx * $moduleWidth, 3), round($bw * $moduleWidth, 3), $height);
        }

        $text = '';
        if ($withText) {
            $text = sprintf(
                '<text x="%s" y="%s" text-anchor="middle" font-family="monospace" font-size="%s">%s</text>',
                $width / 2, $totalHeight - 1, round($textHeight * 0.85, 2),
                htmlspecialchars($data, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" class="code128" width="%s" height="%s" viewBox="0 0 %s %s" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="%s"><g fill="#000">%s</g>%s</svg>',
            $width, $totalHeight, $width, $totalHeight,
            htmlspecialchars($data, ENT_QUOTES, 'UTF-8'), $rects, $text,
        );
    }

    /** EAN-13 check digit for the first 12 digits. */
    public static function ean13CheckDigit(string $twelveDigits): int
    {
        $digits = str_split(substr(preg_replace('/\D/', '', $twelveDigits), 0, 12));
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    /** In-store EAN-13 (prefix 2): '2' + seed padded to 11 digits + check digit. */
    public static function internalEan13(int $seed): string
    {
        $body = '2'.str_pad((string) (abs($seed) % 100_000_000_000), 11, '0', STR_PAD_LEFT);

        return $body.self::ean13CheckDigit($body);
    }
}

<?php

use App\Models\Setting;

if (! function_exists('money')) {
    /**
     * Format an amount using the configured currency settings.
     *
     * Reads currency.symbol / currency.position / currency.decimals from the
     * settings table (cached), so a deployment can render ৳, €, etc. rather
     * than a hardcoded dollar sign.
     */
    function money(mixed $amount, bool $withSymbol = true): string
    {
        $decimals = (int) Setting::get('currency.decimals', 2);
        $formatted = number_format((float) $amount, $decimals);

        if (! $withSymbol) {
            return $formatted;
        }

        $symbol = (string) (Setting::get('currency.symbol', '$') ?? '$');

        return Setting::get('currency.position', 'before') === 'after'
            ? $formatted.$symbol
            : $symbol.$formatted;
    }
}

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

if (! function_exists('format_qty')) {
    /**
     * A stock or sale quantity for display: "12", "0.75 kg", "1.5 ltr".
     *
     * Quantities are stored with 3 decimals so loose goods can be weighed;
     * trailing zeros are dropped so whole-unit items still read as integers.
     */
    function format_qty(mixed $quantity, ?string $unit = null): string
    {
        $formatted = rtrim(rtrim(number_format((float) $quantity, 3, '.', ','), '0'), '.');

        return $unit ? $formatted.' '.$unit : $formatted;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    public static function generate(string $type): string
    {
        $prefixKey = "{$type}.prefix";
        $numberKey = "{$type}.next_number";

        return DB::transaction(function () use ($prefixKey, $numberKey) {
            $prefix = DB::table('settings')->where('key', $prefixKey)->value('value') ?? strtoupper($type);
            $number = (int) DB::table('settings')->where('key', $numberKey)->value('value') ?? 1;
            $year   = now()->format('Y');

            // Increment next number
            DB::table('settings')
                ->where('key', $numberKey)
                ->update(['value' => $number + 1]);

            return sprintf('%s-%s-%04d', $prefix, $year, $number);
        });
    }

    // Usage:
    // NumberGeneratorService::generate('invoice')  → INV-2026-0001
    // NumberGeneratorService::generate('purchase') → PO-2026-0001
    // NumberGeneratorService::generate('payment')  → PAY-2026-0001
}

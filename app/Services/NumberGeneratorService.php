<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    /**
     * Generate the next sequential document number for the given type.
     *
     * The counter row is locked for the duration of the transaction so that
     * concurrent requests cannot read the same value and produce duplicate
     * numbers — invoice_number and order_number are unique columns, so a
     * collision would abort a checkout mid-flight.
     *
     * The sequence restarts at 1 each calendar year; the year the counter
     * currently belongs to is tracked alongside it.
     */
    public static function generate(string $type): string
    {
        $prefixKey = "{$type}.prefix";
        $numberKey = "{$type}.next_number";
        $yearKey = "{$type}.number_year";

        return DB::transaction(function () use ($type, $prefixKey, $numberKey, $yearKey) {
            $year = now()->format('Y');

            $prefix = DB::table('settings')->where('key', $prefixKey)->value('value')
                ?: strtoupper($type);

            // Lock the counter row before reading it so concurrent callers queue.
            $counter = DB::table('settings')
                ->where('key', $numberKey)
                ->lockForUpdate()
                ->first();

            // Note: cast before the fallback — (int) null is 0, so `?? 1` would
            // never fire on a missing row.
            $number = max(1, (int) ($counter->value ?? 0));

            // Restart the sequence when the calendar year rolls over.
            $storedYear = DB::table('settings')->where('key', $yearKey)->value('value');

            if ($storedYear !== null && (string) $storedYear !== $year) {
                $number = 1;
            }

            $now = now();

            if ($counter === null) {
                DB::table('settings')->insert([
                    'key' => $numberKey,
                    'value' => $number + 1,
                    'group' => 'invoice',
                    'type' => 'number',
                    'is_public' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('settings')
                    ->where('key', $numberKey)
                    ->update(['value' => $number + 1, 'updated_at' => $now]);
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $yearKey],
                [
                    'value' => $year,
                    'group' => 'invoice',
                    'type' => 'number',
                    'is_public' => false,
                    'updated_at' => $now,
                ]
            );

            return sprintf('%s-%s-%04d', $prefix, $year, $number);
        });
    }

    // Usage:
    // NumberGeneratorService::generate('invoice')  → INV-2026-0001
    // NumberGeneratorService::generate('purchase') → PO-2026-0001
    // NumberGeneratorService::generate('payment')  → PAY-2026-0001
}

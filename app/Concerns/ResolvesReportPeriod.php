<?php

namespace App\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Shared period filter for report-style Livewire components: a preset
 * (today, this month, ...) or a custom from/to range.
 */
trait ResolvesReportPeriod
{
    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';

    /**
     * Livewire hook: switching to "custom" starts from the range that was on
     * screen, and paginated lists go back to page 1.
     */
    public function updatedPeriod(): void
    {
        if ($this->period === 'custom' && ($this->dateFrom === '' || $this->dateTo === '')) {
            [$from, $to] = $this->periodRange();
            $this->dateFrom = $from->format('Y-m-d');
            $this->dateTo = $to->format('Y-m-d');
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** @return array<string, string> */
    public function periodOptions(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Range',
        ];
    }

    /**
     * Start and end of the selected period (start of day / end of day).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function periodRange(): array
    {
        [$from, $to] = match ($this->period) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [
                $this->parseDate($this->dateFrom) ?? now()->startOfMonth(),
                $this->parseDate($this->dateTo) ?? today(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * The period of the same length immediately before the selected one.
     * Calendar months/years compare against the whole previous month/year.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function previousPeriodRange(): array
    {
        [$from, $to] = $this->periodRange();

        return match ($this->period) {
            'this_month', 'last_month' => [
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $from->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [$from->copy()->subYear()->startOfYear(), $from->copy()->subYear()->endOfYear()],
            default => (function () use ($from, $to) {
                $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

                return [
                    $from->copy()->subDays($days)->startOfDay(),
                    $from->copy()->subDay()->endOfDay(),
                ];
            })(),
        };
    }

    /** Human label for the selected period, e.g. "1 Oct 2026 – 31 Oct 2026". */
    public function periodLabel(): string
    {
        [$from, $to] = $this->periodRange();

        return $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M Y').' – '.$to->format('d M Y');
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        if ($value === '') {
            return null;
        }

        try {
            return Date::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

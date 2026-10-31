<?php

namespace App\Livewire\Reports;

use App\Concerns\ResolvesReportPeriod;
use App\Services\ProfitLossService;
use Carbon\CarbonInterface;
use Livewire\Component;

/**
 * Profit & loss for a period, written for shop owners rather than accountants.
 * The figures come from ProfitLossService, which the partnership report shares.
 */
class ProfitLossReport extends Component
{
    use ResolvesReportPeriod;

    /** Sale statuses that never count as revenue. */
    public const EXCLUDED_STATUSES = ProfitLossService::EXCLUDED_STATUSES;

    /**
     * Every figure of the statement for one period.
     *
     * @return array<string, mixed>
     */
    public function statement(CarbonInterface $from, CarbonInterface $to): array
    {
        return app(ProfitLossService::class)->statement($from, $to);
    }

    /**
     * Gross profit per product category, net of returns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categoryBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        return app(ProfitLossService::class)->categoryBreakdown($from, $to);
    }

    /** Percentage change from $previous to $current, or null when there is no base. */
    public static function change(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    public function render()
    {
        [$from, $to] = $this->periodRange();
        [$prevFrom, $prevTo] = $this->previousPeriodRange();

        return view('livewire.reports.profit-loss-report', [
            'current' => $this->statement($from, $to),
            'previous' => $this->statement($prevFrom, $prevTo),
            'categories' => $this->categoryBreakdown($from, $to),
            'rangeLabel' => $this->periodLabel(),
            'previousLabel' => $prevFrom->isSameDay($prevTo)
                ? $prevFrom->format('d M Y')
                : $prevFrom->format('d M Y').' – '.$prevTo->format('d M Y'),
        ])->layout('layouts.app', ['title' => 'Profit & Loss']);
    }
}

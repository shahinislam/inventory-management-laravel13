<?php

namespace App\Livewire\Partners;

use App\Models\Partner;
use App\Models\PartnerPeriod;
use App\Models\PartnerPeriodShare;
use App\Models\PartnerTransaction;
use App\Services\PartnershipService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PartnershipReport extends Component
{
    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showCloseModal = false;

    public string $closeNotes = '';

    public function mount(): void
    {
        $this->setDates();
    }

    public function updatedPeriod(): void
    {
        $this->setDates();
    }

    /** Mirrors SalesReport::setDates() so the filters behave identically. */
    private function setDates(): void
    {
        match ($this->period) {
            'today' => [$this->dateFrom, $this->dateTo] = [today()->format('Y-m-d'), today()->format('Y-m-d')],
            'this_week' => [$this->dateFrom, $this->dateTo] = [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'this_month' => [$this->dateFrom, $this->dateTo] = [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            'last_month' => [$this->dateFrom, $this->dateTo] = [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
            'this_year' => [$this->dateFrom, $this->dateTo] = [now()->startOfYear()->format('Y-m-d'), now()->endOfYear()->format('Y-m-d')],
            'custom' => null,
            default => null,
        };
    }

    /** The period currently on screen, if it has already been settled. */
    public function getClosedPeriodProperty(): ?PartnerPeriod
    {
        return PartnerPeriod::where('period_start', $this->dateFrom)
            ->where('period_end', $this->dateTo)
            ->first();
    }

    /**
     * Freeze the figures on screen so later edits to the underlying invoices
     * cannot change what the partners settled against.
     */
    public function closePeriod(): void
    {
        if (! auth()->user()?->hasRole(['admin'])) {
            session()->flash('error', 'Only an admin may close a period.');

            return;
        }

        if ($this->closedPeriod) {
            session()->flash('error', 'This period is already closed.');

            return;
        }

        $summary = app(PartnershipService::class)->summary($this->dateFrom, $this->dateTo);
        $partners = Partner::active()->orderBy('name')->get();

        if ($partners->isEmpty()) {
            session()->flash('error', 'Add at least one active partner before closing a period.');

            return;
        }

        DB::transaction(function () use ($summary, $partners) {
            $periodRecord = PartnerPeriod::create([
                'period_start' => $this->dateFrom,
                'period_end' => $this->dateTo,
                'total_sales' => $summary['total_sales'],
                'total_purchases' => $summary['total_purchases'],
                'courier_margin' => $summary['courier_margin'],
                'profit' => $summary['profit'],
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes' => $this->closeNotes ?: null,
            ]);

            foreach ($partners as $partner) {
                PartnerPeriodShare::create([
                    'partner_period_id' => $periodRecord->id,
                    'partner_id' => $partner->id,
                    // Snapshotted: a partner's share may be changed later.
                    'share_percentage' => $partner->share_percentage,
                    'profit_share' => round($summary['profit'] * (float) $partner->share_percentage / 100, 2),
                ]);
            }
        });

        $this->showCloseModal = false;
        $this->closeNotes = '';
        session()->flash('success', 'Period closed and partner shares locked.');
    }

    public function render()
    {
        $summary = app(PartnershipService::class)->summary($this->dateFrom, $this->dateTo);

        $partners = Partner::active()->orderBy('name')->get();
        $closed = $this->closedPeriod?->load('shares');

        // Investment and withdrawal balances are lifetime figures, not
        // period-scoped: a partner's capital account carries across periods.
        $invested = PartnerTransaction::investments()
            ->select('partner_id', DB::raw('SUM(amount) as total'))
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        $withdrawn = PartnerTransaction::withdrawals()
            ->select('partner_id', DB::raw('SUM(amount) as total'))
            ->groupBy('partner_id')
            ->pluck('total', 'partner_id');

        $rows = $partners->map(function (Partner $partner) use ($summary, $closed, $invested, $withdrawn) {
            // A closed period pays out the locked figure; an open one shows the
            // live estimate at the partner's current share.
            $lockedShare = $closed?->shares->firstWhere('partner_id', $partner->id);

            $share = $lockedShare
                ? (float) $lockedShare->share_percentage
                : (float) $partner->share_percentage;

            $profitShare = $lockedShare
                ? (float) $lockedShare->profit_share
                : round($summary['profit'] * $share / 100, 2);

            $in = (float) ($invested[$partner->id] ?? 0);
            $out = (float) ($withdrawn[$partner->id] ?? 0);

            return [
                'partner' => $partner,
                'share' => $share,
                'profit_share' => $profitShare,
                'invested' => $in,
                'withdrawn' => $out,
                'balance' => round($in + $profitShare - $out, 2),
            ];
        });

        return view('livewire.partners.partnership-report', [
            'summary' => $summary,
            'rows' => $rows,
            'closed' => $closed,
            'totalShare' => Partner::totalActiveShare(),
            'recentPeriods' => PartnerPeriod::with('closedBy')
                ->orderByDesc('period_start')
                ->limit(12)
                ->get(),
        ])->layout('layouts.app', ['title' => 'Partnership Report']);
    }
}

<?php

namespace App\Livewire\Reports;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Setting;
use App\Models\StockBatch;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Batches with an expiry date: what has gone off, what is about to, and a
 * one-click write-off for expired stock.
 */
class ExpiryReport extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'expired'; // expired, soon, all

    public string $days = '30';

    public string $warehouseFilter = '';

    public string $search = '';

    public function mount(): void
    {
        $this->days = (string) (int) Setting::get('notification.days', 30);
    }

    public function updated($property): void
    {
        if (in_array($property, ['tab', 'days', 'warehouseFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['expired', 'soon', 'all'], true) ? $tab : 'expired';
        $this->resetPage();
    }

    private function daysWindow(): int
    {
        return max(0, min(3650, (int) $this->days));
    }

    private function baseQuery()
    {
        return StockBatch::query()
            ->where('quantity', '>', 0)
            ->when($this->warehouseFilter, fn ($q) => $q->where('warehouse_id', $this->warehouseFilter))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('sku', 'like', '%'.trim($this->search).'%')
                ->orWhere('barcode', trim($this->search))));
    }

    /**
     * Take an expired batch out of stock.
     *
     * InventoryService removes earliest-expiry first (FEFO), so the units
     * taken come from the oldest batch of this product in this warehouse —
     * for an expired batch that is normally this very batch. The quantity is
     * capped at what the warehouse actually holds.
     */
    public function writeOff(int $batchId, InventoryService $inventory): void
    {
        abort_unless(auth()->user()->hasRole(['admin', 'manager']), 403);

        $batch = StockBatch::with('product')->findOrFail($batchId);
        $quantity = min((float) $batch->quantity, $inventory->stockIn($batch->product_id, $batch->warehouse_id));

        if ($quantity <= 0) {
            session()->flash('error', 'Nothing left to write off for this batch.');

            return;
        }

        DB::transaction(fn () => $inventory->remove($batch->product_id, $batch->warehouse_id, $quantity, 'expired', [
            'notes' => 'Expired batch '.($batch->batch_number ?: '(no number)').' written off',
        ]));

        DashboardIndex::flushCache();

        session()->flash('success', 'Wrote off '.format_qty($quantity, $batch->product?->unit)." of {$batch->product?->name}.");
    }

    public function render()
    {
        $days = $this->daysWindow();

        $batches = $this->baseQuery()
            ->with(['product', 'warehouse'])
            ->when($this->tab === 'expired', fn ($q) => $q->expired())
            ->when($this->tab === 'soon', fn ($q) => $q->expiringWithin($days))
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->paginate(25);

        $expired = $this->baseQuery()->expired()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(quantity * unit_cost), 0) as v')->first();
        $soon = $this->baseQuery()->expiringWithin($days)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(quantity * unit_cost), 0) as v')->first();

        return view('livewire.reports.expiry-report', [
            'batches' => $batches,
            'warehouses' => Warehouse::active()->get(),
            'expiredCount' => (int) $expired->n,
            'expiredValue' => (float) $expired->v,
            'soonCount' => (int) $soon->n,
            'soonValue' => (float) $soon->v,
            'window' => $days,
            'canWriteOff' => auth()->user()->hasRole(['admin', 'manager']),
            'alertsOn' => (bool) Setting::get('notification.expiry', true),
        ])->layout('layouts.app', ['title' => 'Expiry Report']);
    }
}

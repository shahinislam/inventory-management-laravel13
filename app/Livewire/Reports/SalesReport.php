<?php

namespace App\Livewire\Reports;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\ProfitLossService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SalesReport extends Component
{
    use WithPagination;

    public string $period = 'this_month';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->setDates();
    }

    public function updatedPeriod(): void
    {
        $this->setDates();
        $this->resetPage();
    }

    private function setDates(): void
    {
        match ($this->period) {
            'today' => [$this->dateFrom, $this->dateTo] = [today()->format('Y-m-d'), today()->format('Y-m-d')],
            'yesterday' => [$this->dateFrom, $this->dateTo] = [today()->subDay()->format('Y-m-d'), today()->subDay()->format('Y-m-d')],
            'this_week' => [$this->dateFrom, $this->dateTo] = [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'this_month' => [$this->dateFrom, $this->dateTo] = [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            'last_month' => [$this->dateFrom, $this->dateTo] = [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
            'this_year' => [$this->dateFrom, $this->dateTo] = [now()->startOfYear()->format('Y-m-d'), now()->endOfYear()->format('Y-m-d')],
            'custom' => null,
            default => null,
        };
    }

    public function render()
    {
        // Every issued sale counts, paid or still owed, like the Profit & Loss;
        // drafts, cancelled sales, return notes and parked POS sales do not. A
        // fully returned sale still counts, at its net value of zero.
        $query = Invoice::sales()
            ->whereNotIn('invoices.status', ProfitLossService::EXCLUDED_STATUSES)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('invoice_date', '<=', $this->dateTo));

        $summary = [
            'total_sales' => (clone $query)->sum(DB::raw('total - returned_amount')),
            'total_returns' => (clone $query)->sum('returned_amount'),
            'total_invoices' => (clone $query)->count(),
            'total_paid' => (clone $query)->sum('paid_amount'),
            'total_due' => (clone $query)->sum('due_amount'),
            'avg_invoice' => (clone $query)->avg(DB::raw('total - returned_amount')) ?? 0,
        ];

        // Daily breakdown
        $dailySales = (clone $query)
            ->selectRaw('DATE(invoice_date) as date, COUNT(*) as count, SUM(total - returned_amount) as total, SUM(paid_amount) as paid')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // How much of each sold line has come back on a return note, whenever.
        $returnedQty = InvoiceItem::query()
            ->whereIn('invoice_items.invoice_id', Invoice::query()->returns()
                ->whereNotIn('invoices.status', ProfitLossService::EXCLUDED_STATUSES)
                ->select('invoices.id'))
            ->whereNotNull('invoice_items.parent_item_id')
            ->selectRaw('invoice_items.parent_item_id, SUM(invoice_items.quantity) as qty')
            ->groupBy('invoice_items.parent_item_id');

        // Top products by goods value: the line after its item discount, less the
        // tax inside it and less the share brought back. Bill discounts are not
        // spread over the lines, so the column sums to more than net sales.
        $kept = '(invoice_items.quantity - COALESCE(returned.qty, 0))';
        $topProducts = InvoiceItem::query()
            ->whereIn('invoice_items.invoice_id', (clone $query)->select('invoices.id'))
            ->leftJoinSub($returnedQty, 'returned', 'returned.parent_item_id', '=', 'invoice_items.id')
            ->selectRaw("invoice_items.product_name, invoice_items.product_sku, SUM({$kept}) as total_qty,
                SUM(CASE WHEN invoice_items.quantity > 0
                    THEN (invoice_items.subtotal - invoice_items.tax_amount) * {$kept} / invoice_items.quantity
                    ELSE 0 END) as total_revenue")
            ->groupBy('invoice_items.product_name', 'invoice_items.product_sku')
            ->having('total_qty', '>', 0)
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return view('livewire.reports.sales-report', compact('summary', 'dailySales', 'topProducts'))
            ->layout('layouts.app', ['title' => 'Sales Report']);
    }
}

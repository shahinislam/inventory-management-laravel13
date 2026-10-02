<?php

namespace App\Livewire\Reports;

use App\Models\Invoice;
use App\Models\InvoiceItem;
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
        // Real sales only (no return notes or parked POS sales); a fully
        // returned sale still counts, at its net value of zero.
        $query = Invoice::sales()
            ->whereIn('status', ['paid', 'partial', 'returned'])
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

        // Top products
        $topProducts = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.type', 'sale')
            ->where('invoices.is_held', false)
            ->whereIn('invoices.status', ['paid', 'partial', 'returned'])
            ->when($this->dateFrom, fn ($q) => $q->whereDate('invoices.invoice_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('invoices.invoice_date', '<=', $this->dateTo))
            ->selectRaw('product_name, product_sku, SUM(invoice_items.quantity) as total_qty, SUM(invoice_items.subtotal) as total_revenue')
            ->groupBy('product_name', 'product_sku')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return view('livewire.reports.sales-report', compact('summary', 'dailySales', 'topProducts'))
            ->layout('layouts.app', ['title' => 'Sales Report']);
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ProfitLossService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public string $period = 'today';

    public function render()
    {
        return view('livewire.dashboard.index', [
            'stats' => $this->getStats(),
            'recentInvoices' => $this->getRecentInvoices(),
            'lowStockProducts' => $this->getLowStockProducts(),
            'recentMovements' => $this->getRecentMovements(),
            'salesChart' => $this->getSalesChart(),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }

    private function getStats(): array
    {
        return Cache::remember("dashboard_stats_{$this->period}", 300, function () {
            $dateFilter = match ($this->period) {
                'today' => today(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                default => today(),
            };

            $sales = self::issuedSales()->whereDate('invoice_date', '>=', $dateFilter);

            $totalSales = (float) (clone $sales)->sum(DB::raw('total - returned_amount'));
            $totalInvoices = (clone $sales)->count();
            $totalProducts = Product::active()->count();
            $lowStock = Product::lowStock()->count();
            $totalCustomers = Customer::active()->count();
            $totalSuppliers = Supplier::active()->count();
            $pendingOrders = PurchaseOrder::pending()->count();
            $totalRevenue = (float) self::issuedSales()->sum(DB::raw('total - returned_amount'));

            return compact(
                'totalSales', 'totalInvoices', 'totalProducts',
                'lowStock', 'totalCustomers', 'totalSuppliers',
                'pendingOrders', 'totalRevenue'
            );
        });
    }

    private function getRecentInvoices()
    {
        return Invoice::where('is_held', false)->with(['customer', 'createdBy'])
            ->latest()
            ->take(5)
            ->get();
    }

    private function getLowStockProducts()
    {
        return Product::with(['category', 'supplier'])
            ->lowStock()
            ->active()
            ->take(5)
            ->get();
    }

    private function getRecentMovements()
    {
        return StockMovement::with(['product', 'createdBy'])
            ->latest()
            ->take(5)
            ->get();
    }

    private function getSalesChart(): array
    {
        return Cache::remember('dashboard_sales_chart', 300, function () {
            // One grouped query for the whole week rather than a sum() per day.
            $totals = self::issuedSales()
                ->whereDate('invoice_date', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(invoice_date) as day, SUM(total - returned_amount) as sales')
                ->groupBy('day')
                ->pluck('sales', 'day');

            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $data[] = [
                    'date' => $date->format('D'),
                    'sales' => (float) ($totals[$date->format('Y-m-d')] ?? 0),
                ];
            }

            return $data;
        });
    }

    /**
     * Every issued sale, paid or still owed, as the Profit & Loss counts them.
     * Sales figures are what was billed less anything brought back.
     */
    private static function issuedSales(): Builder
    {
        return Invoice::query()->sales()->whereNotIn('invoices.status', ProfitLossService::EXCLUDED_STATUSES);
    }

    /**
     * Switching period should reuse that period's cached stats, not discard them.
     */
    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    /**
     * Clear every dashboard cache entry. Call this after any write that changes
     * sales, stock or order counts — clearing only the "today" key leaves the
     * week/month stats and the sales chart stale.
     */
    public static function flushCache(): void
    {
        foreach (['today', 'week', 'month'] as $period) {
            Cache::forget("dashboard_stats_{$period}");
        }

        Cache::forget('dashboard_sales_chart');
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Index extends Component
{
    public string $period = 'today';

    public function render()
    {
        return view('livewire.dashboard.index', [
            'stats'           => $this->getStats(),
            'recentInvoices'  => $this->getRecentInvoices(),
            'lowStockProducts'=> $this->getLowStockProducts(),
            'recentMovements' => $this->getRecentMovements(),
            'salesChart'      => $this->getSalesChart(),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }

    private function getStats(): array
    {
        return Cache::remember("dashboard_stats_{$this->period}", 300, function () {
            $dateFilter = match($this->period) {
                'today'   => today(),
                'week'    => now()->startOfWeek(),
                'month'   => now()->startOfMonth(),
                default   => today(),
            };

            $totalSales    = Invoice::where('status', 'paid')->whereDate('invoice_date', '>=', $dateFilter)->sum('total');
            $totalInvoices = Invoice::whereDate('invoice_date', '>=', $dateFilter)->count();
            $totalProducts = Product::active()->count();
            $lowStock      = Product::lowStock()->count();
            $totalCustomers= Customer::active()->count();
            $totalSuppliers= Supplier::active()->count();
            $pendingOrders = PurchaseOrder::pending()->count();
            $totalRevenue  = Invoice::where('status', 'paid')->sum('total');

            return compact(
                'totalSales', 'totalInvoices', 'totalProducts',
                'lowStock', 'totalCustomers', 'totalSuppliers',
                'pendingOrders', 'totalRevenue'
            );
        });
    }

    private function getRecentInvoices()
    {
        return Invoice::with(['customer', 'createdBy'])
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
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $data[] = [
                    'date'  => $date->format('D'),
                    'sales' => Invoice::where('status', 'paid')
                        ->whereDate('invoice_date', $date)
                        ->sum('total'),
                ];
            }
            return $data;
        });
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        Cache::forget("dashboard_stats_{$period}");
    }
}

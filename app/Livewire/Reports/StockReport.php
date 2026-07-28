<?php

namespace App\Livewire\Reports;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockReport extends Component
{
    use WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $stockFilter = '';

    /** Empty means "all warehouses combined". */
    public string $warehouseFilter = '';

    public string $sortBy = 'name';

    public string $sortDir = 'asc';

    public function updatingWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $col): void
    {
        $this->sortDir = $this->sortBy === $col
            ? ($this->sortDir === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sortBy = $col;
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
            ))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->stockFilter === 'low', fn ($q) => $q->lowStock())
            ->when($this->stockFilter === 'out', fn ($q) => $q->where('quantity', 0))
            ->when($this->stockFilter === 'ok', fn ($q) => $q->whereColumn('quantity', '>', 'min_stock_level'))
            // Narrow to one location: only products actually held there.
            ->when($this->warehouseFilter, fn ($q) => $q
                ->whereHas('warehouses', fn ($w) => $w
                    ->where('warehouses.id', $this->warehouseFilter)
                    ->where('product_warehouse.quantity', '>', 0))
                ->with(['warehouses' => fn ($w) => $w->where('warehouses.id', $this->warehouseFilter)]))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        $summary = $this->summaryFor($this->warehouseFilter ?: null);

        $categories = Category::active()->get();
        $warehouses = Warehouse::active()->get();

        return view('livewire.reports.stock-report', compact('products', 'summary', 'categories', 'warehouses'))
            ->layout('layouts.app', ['title' => 'Stock Report']);
    }

    /**
     * Totals for one warehouse, or across all of them when none is selected.
     */
    private function summaryFor(?string $warehouseId): array
    {
        if (! $warehouseId) {
            return [
                'total_products' => Product::active()->count(),
                'total_value' => Product::active()->selectRaw('SUM(quantity * cost_price) as val')->value('val') ?? 0,
                'retail_value' => Product::active()->selectRaw('SUM(quantity * selling_price) as val')->value('val') ?? 0,
                'low_stock' => Product::lowStock()->count(),
                'out_of_stock' => Product::where('quantity', 0)->count(),
            ];
        }

        // Value the stock that physically sits in this warehouse.
        $stock = DB::table('product_warehouse as pw')
            ->join('products as p', 'p.id', '=', 'pw.product_id')
            ->where('pw.warehouse_id', $warehouseId)
            ->whereNull('p.deleted_at')
            ->where('p.status', 'active');

        return [
            'total_products' => (clone $stock)->where('pw.quantity', '>', 0)->count(),
            'total_value' => (clone $stock)->sum(DB::raw('pw.quantity * p.cost_price')),
            'retail_value' => (clone $stock)->sum(DB::raw('pw.quantity * p.selling_price')),
            'low_stock' => (clone $stock)->whereColumn('pw.quantity', '<=', 'p.min_stock_level')
                ->where('pw.quantity', '>', 0)->count(),
            'out_of_stock' => (clone $stock)->where('pw.quantity', 0)->count(),
        ];
    }
}

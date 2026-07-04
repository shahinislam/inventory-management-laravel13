<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;

class StockReport extends Component
{
    use WithPagination;

    public string $search         = '';
    public string $categoryFilter = '';
    public string $stockFilter    = '';
    public string $sortBy         = 'name';
    public string $sortDir        = 'asc';

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
            ->when($this->search, fn($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
            )
            ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->stockFilter === 'low', fn($q) => $q->lowStock())
            ->when($this->stockFilter === 'out', fn($q) => $q->where('quantity', 0))
            ->when($this->stockFilter === 'ok', fn($q) => $q->whereColumn('quantity', '>', 'min_stock_level'))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(20);

        $summary = [
            'total_products'   => Product::active()->count(),
            'total_value'      => Product::active()->selectRaw('SUM(quantity * cost_price) as val')->value('val') ?? 0,
            'retail_value'     => Product::active()->selectRaw('SUM(quantity * selling_price) as val')->value('val') ?? 0,
            'low_stock'        => Product::lowStock()->count(),
            'out_of_stock'     => Product::where('quantity', 0)->count(),
        ];

        $categories = Category::active()->get();

        return view('livewire.reports.stock-report', compact('products', 'summary', 'categories'))
            ->layout('layouts.app', ['title' => 'Stock Report']);
    }
}
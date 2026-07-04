<?php

namespace App\Livewire\Reports;

use App\Models\Product;
use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;

class LowStockAlert extends Component
{
    use WithPagination;

    public string $search         = '';
    public string $categoryFilter = '';
    public string $alertType      = 'all'; // all, low, out

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->active()
            ->where(function ($q) {
                $q->whereColumn('quantity', '<=', 'min_stock_level')
                  ->orWhere('quantity', 0);
            })
            ->when($this->search, fn($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
            )
            ->when($this->categoryFilter, fn($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->alertType === 'out', fn($q) => $q->where('quantity', 0))
            ->when($this->alertType === 'low', fn($q) => $q->where('quantity', '>', 0))
            ->orderBy('quantity')
            ->paginate(20);

        $categories  = Category::active()->get();
        $outOfStock  = Product::active()->where('quantity', 0)->count();
        $lowStock    = Product::active()->lowStock()->where('quantity', '>', 0)->count();

        return view('livewire.reports.low-stock-alert', compact('products', 'categories', 'outOfStock', 'lowStock'))
            ->layout('layouts.app', ['title' => 'Low Stock Alerts']);
    }
}
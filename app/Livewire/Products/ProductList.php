<?php

namespace App\Livewire\Products;

use App\Concerns\AuthorizesDestructiveActions;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use AuthorizesDestructiveActions, WithPagination;

    public string $search = '';

    public string $categoryFilter = '';

    public string $supplierFilter = '';

    public string $statusFilter = '';

    public string $stockFilter = '';

    public string $sortBy = 'name';

    public string $sortDir = 'asc';

    public bool $showFilters = false;

    public ?int $deleteId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortBy' => ['except' => 'name'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if (! $this->canDelete()) {
            return;
        }

        if ($this->deleteId) {
            Product::findOrFail($this->deleteId)->delete();
            DashboardIndex::flushCache();
            $this->deleteId = null;
            $this->dispatch('notify', message: 'Product deleted successfully!', type: 'success');
        }
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'supplier', 'media'])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
                ->orWhere('barcode', 'like', "%{$this->search}%")
            ))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->supplierFilter, fn ($q) => $q->where('supplier_id', $this->supplierFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->stockFilter === 'low', fn ($q) => $q->lowStock())
            ->when($this->stockFilter === 'out', fn ($q) => $q->where('quantity', 0))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);

        $categories = Cache::remember('categories_list', 3600, fn () => Category::active()->ordered()->get());
        $suppliers = Cache::remember('suppliers_list', 3600, fn () => Supplier::active()->get());

        return view('livewire.products.product-list', compact('products', 'categories', 'suppliers'))
            ->layout('layouts.app', ['title' => 'Products']);
    }
}

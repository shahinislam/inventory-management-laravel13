<?php

namespace App\Livewire\Stock;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Component;

class StockAdjustment extends Component
{
    public string $search = '';

    public ?int $product_id = null;

    public ?int $warehouse_id = null;

    public string $adjustment_type = 'add'; // add, remove, set

    public string $quantity = '';

    public string $reason = '';

    public string $notes = '';

    public ?Product $selectedProduct = null;

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::getDefault()?->id;
    }

    protected function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'adjustment_type' => 'required|in:add,remove,set',
            'quantity' => 'required|integer|min:0',
            'reason' => 'required|string|max:200',
            'notes' => 'nullable|string',
        ];
    }

    public function updatedSearch(): void
    {
        if (empty($this->search)) {
            $this->selectedProduct = null;
            $this->product_id = null;
        }
    }

    public function getSearchResultsProperty()
    {
        if (! $this->search || $this->selectedProduct) {
            return collect();
        }

        return Product::where('name', 'like', "%{$this->search}%")
            ->orWhere('sku', 'like', "%{$this->search}%")
            ->orWhere('barcode', 'like', "%{$this->search}%")
            ->limit(10)
            ->get();
    }

    public function selectProduct(int $id): void
    {
        $this->selectedProduct = Product::find($id);
        $this->product_id = $id;
        $this->search = $this->selectedProduct->name.' ('.$this->selectedProduct->sku.')';
    }

    public function selectHighlighted(int $index): void
    {
        $product = $this->searchResults->values()->get($index);
        if ($product) {
            $this->selectProduct($product->id);
        }
    }

    public function clearProduct(): void
    {
        $this->selectedProduct = null;
        $this->product_id = null;
        $this->search = '';
    }

    public function save(): void
    {
        $this->validate();

        $qty = (int) $this->quantity;
        $inventory = app(InventoryService::class);

        $product = Product::findOrFail($this->product_id);
        $before = $inventory->stockIn($this->product_id, $this->warehouse_id);

        $notes = $this->reason.($this->notes ? " - {$this->notes}" : '');

        // Adjustments always target one warehouse — "how much is in this room".
        match ($this->adjustment_type) {
            'add' => $inventory->add($this->product_id, $this->warehouse_id, $qty, 'adjustment', ['notes' => $notes]),
            'remove' => $inventory->remove($this->product_id, $this->warehouse_id, min($qty, $before), 'adjustment', ['notes' => $notes]),
            'set' => $inventory->setTo($this->product_id, $this->warehouse_id, $qty, ['notes' => $notes]),
        };

        $after = $inventory->stockIn($this->product_id, $this->warehouse_id);

        DashboardIndex::flushCache();

        session()->flash('success', "Stock adjusted: {$product->name} from {$before} to {$after}");
        $this->reset(['product_id', 'quantity', 'reason', 'notes', 'search', 'selectedProduct']);
        $this->adjustment_type = 'add';
    }

    public function render()
    {
        $products = $this->searchResults;
        $warehouses = Warehouse::active()->get();

        return view('livewire.stock.stock-adjustment', compact('products', 'warehouses'))
            ->layout('layouts.app', ['title' => 'Stock Adjustment']);
    }
}

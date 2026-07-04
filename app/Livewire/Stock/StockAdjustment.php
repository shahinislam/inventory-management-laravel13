<?php

namespace App\Livewire\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockAdjustment extends Component
{
    public string $search       = '';
    public ?int $product_id     = null;
    public ?int $warehouse_id   = null;
    public string $adjustment_type = 'add'; // add, remove, set
    public string $quantity     = '';
    public string $reason       = '';
    public string $notes        = '';
    public ?Product $selectedProduct = null;

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::getDefault()?->id;
    }

    protected function rules(): array
    {
        return [
            'product_id'      => 'required|exists:products,id',
            'warehouse_id'    => 'nullable|exists:warehouses,id',
            'adjustment_type' => 'required|in:add,remove,set',
            'quantity'        => 'required|integer|min:0',
            'reason'          => 'required|string|max:200',
            'notes'           => 'nullable|string',
        ];
    }

    public function updatedSearch(): void
    {
        if (empty($this->search)) {
            $this->selectedProduct = null;
            $this->product_id      = null;
        }
    }

    public function getSearchResultsProperty()
    {
        if (!$this->search || $this->selectedProduct) return collect();

        return Product::where('name', 'like', "%{$this->search}%")
            ->orWhere('sku', 'like', "%{$this->search}%")
            ->orWhere('barcode', 'like', "%{$this->search}%")
            ->limit(10)
            ->get();
    }

    public function selectProduct(int $id): void
    {
        $this->selectedProduct = Product::find($id);
        $this->product_id      = $id;
        $this->search          = $this->selectedProduct->name . ' (' . $this->selectedProduct->sku . ')';
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
        $this->product_id      = null;
        $this->search          = '';
    }

    public function save(): void
    {
        $this->validate();

        $product = Product::findOrFail($this->product_id);
        $before  = $product->quantity;
        $qty     = (int) $this->quantity;

        $after = match($this->adjustment_type) {
            'add'    => $before + $qty,
            'remove' => max(0, $before - $qty),
            'set'    => $qty,
        };

        $changeQty = abs($after - $before);

        if ($changeQty === 0) {
            session()->flash('error', 'No change in quantity.');
            return;
        }

        DB::transaction(function () use ($product, $before, $after, $changeQty) {
            $product->update(['quantity' => $after]);

            StockMovement::create([
                'product_id'      => $product->id,
                'warehouse_id'    => $this->warehouse_id,
                'created_by'      => auth()->id(),
                'type'            => 'adjustment',
                'quantity'        => $changeQty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'notes'           => $this->reason . ($this->notes ? " - {$this->notes}" : ''),
            ]);
        });

        Cache::forget('dashboard_stats_today');

        session()->flash('success', "Stock adjusted: {$product->name} from {$before} to {$after}");
        $this->reset(['product_id', 'quantity', 'reason', 'notes', 'search', 'selectedProduct']);
        $this->adjustment_type = 'add';
    }

    public function render()
    {
        $products   = $this->searchResults;
        $warehouses = Warehouse::active()->get();

        return view('livewire.stock.stock-adjustment', compact('products', 'warehouses'))
            ->layout('layouts.app', ['title' => 'Stock Adjustment']);
    }
}
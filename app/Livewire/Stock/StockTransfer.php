<?php

namespace App\Livewire\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockTransfer extends Component
{
    public string $search            = '';
    public ?int $product_id          = null;
    public ?int $from_warehouse_id   = null;
    public ?int $to_warehouse_id     = null;
    public string $quantity          = '';
    public string $notes             = '';
    public ?Product $selectedProduct = null;

    public function mount(): void
    {
        $this->from_warehouse_id = Warehouse::getDefault()?->id;
    }

    protected function rules(): array
    {
        return [
            'product_id'        => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id'   => 'required|exists:warehouses,id',
            'quantity'          => 'required|integer|min:1',
            'notes'             => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'from_warehouse_id.different' => 'Source and destination warehouses must be different.',
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
        $qty     = (int) $this->quantity;

        if ($qty > $product->quantity) {
            $this->addError('quantity', "Only {$product->quantity} {$product->unit} available in stock.");
            return;
        }

        DB::transaction(function () use ($product, $qty) {
            $before = $product->quantity;
            $after  = $before - $qty;
            $product->update(['quantity' => $after]);

            // Transfer Out
            StockMovement::create([
                'product_id'      => $product->id,
                'warehouse_id'    => $this->from_warehouse_id,
                'created_by'      => auth()->id(),
                'type'            => 'transfer_out',
                'quantity'        => $qty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'notes'           => $this->notes,
            ]);

            // Transfer In
            StockMovement::create([
                'product_id'      => $product->id,
                'warehouse_id'    => $this->to_warehouse_id,
                'created_by'      => auth()->id(),
                'type'            => 'transfer_in',
                'quantity'        => $qty,
                'before_quantity' => $before,
                'after_quantity'  => $after,
                'notes'           => $this->notes,
            ]);
        });

        Cache::forget('dashboard_stats_today');

        $fromName = Warehouse::find($this->from_warehouse_id)->name;
        $toName   = Warehouse::find($this->to_warehouse_id)->name;

        session()->flash('success', "Transferred {$qty} {$product->unit} of {$product->name} from {$fromName} to {$toName}");
        $this->reset(['product_id', 'quantity', 'notes', 'search', 'selectedProduct', 'to_warehouse_id']);
    }

    public function render()
    {
        $products   = $this->searchResults;
        $warehouses = Warehouse::active()->get();

        return view('livewire.stock.stock-transfer', compact('products', 'warehouses'))
            ->layout('layouts.app', ['title' => 'Stock Transfer']);
    }
}
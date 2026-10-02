<?php

namespace App\Livewire\Stock;

use App\Concerns\HandlesBarcodeScans;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Component;

class StockTransfer extends Component
{
    use HandlesBarcodeScans;

    public string $search = '';

    public ?int $product_id = null;

    public ?int $from_warehouse_id = null;

    public ?int $to_warehouse_id = null;

    public string $quantity = '';

    public string $notes = '';

    public ?Product $selectedProduct = null;

    public function mount(): void
    {
        $this->from_warehouse_id = Warehouse::getDefault()?->id;
    }

    protected function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'from_warehouse_id' => 'required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => $this->selectedProduct?->isLoose() ? 'required|numeric|min:0.001' : 'required|integer|min:1',
            'notes' => 'nullable|string',
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
            $this->product_id = null;
        }
    }

    public function getSearchResultsProperty()
    {
        if (! $this->search || $this->selectedProduct) {
            return collect();
        }

        return Product::where(fn ($s) => $s
            ->where('name', 'like', "%{$this->search}%")
            ->orWhere('sku', 'like', "%{$this->search}%")
            ->orWhere('barcode', 'like', "%{$this->search}%"))
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

        $product = Product::findOrFail($this->product_id);
        $qty = round((float) $this->quantity, 3);
        $inventory = app(InventoryService::class);

        $available = $inventory->stockIn($this->product_id, $this->from_warehouse_id);

        if ($qty > $available) {
            $this->addError('quantity', 'Only '.format_qty($available, $product->unit).' available in the source warehouse.');

            return;
        }

        // Takes the stock out of one warehouse and puts it into the other. The
        // total on hand is unchanged; only its location moves.
        $inventory->transfer(
            productId: $this->product_id,
            fromWarehouseId: $this->from_warehouse_id,
            toWarehouseId: $this->to_warehouse_id,
            quantity: $qty,
            notes: $this->notes ?: null,
        );

        DashboardIndex::flushCache();

        $fromName = Warehouse::find($this->from_warehouse_id)->name;
        $toName = Warehouse::find($this->to_warehouse_id)->name;

        session()->flash('success', "Transferred {$qty} {$product->unit} of {$product->name} from {$fromName} to {$toName}");
        $this->reset(['product_id', 'quantity', 'notes', 'search', 'selectedProduct', 'to_warehouse_id']);
    }

    protected function handleScan(string $code): bool
    {
        $product = $this->findScannedProduct($code);

        if (! $product) {
            return false;
        }

        $this->selectProduct($product->id);

        return true;
    }

    public function render()
    {
        $products = $this->searchResults;
        $warehouses = Warehouse::active()->get();

        return view('livewire.stock.stock-transfer', compact('products', 'warehouses'))
            ->layout('layouts.app', ['title' => 'Stock Transfer']);
    }
}

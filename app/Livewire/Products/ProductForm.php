<?php

namespace App\Livewire\Products;

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;

    // Form fields
    public string $name = '';

    public string $sku = '';

    public string $barcode = '';

    public string $description = '';

    public ?int $category_id = null;

    public ?int $supplier_id = null;

    public ?int $media_id = null;

    public string $cost_price = '0';

    public string $selling_price = '0';

    public string $tax_rate = '0';

    public string $discount = '0';

    public string $discount_type = 'percentage';

    public string $quantity = '0';

    public string $min_stock_level = '0';

    public string $unit = 'pcs';

    public string $weight = '';

    public string $dimensions = '';

    public string $status = 'active';

    public bool $showMediaPicker = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'sku' => 'nullable|string|max:100|unique:products,sku,'.($this->product?->id ?? 'NULL'),
            'barcode' => 'nullable|string|max:100|unique:products,barcode,'.($this->product?->id ?? 'NULL'),
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'media_id' => 'nullable|exists:media,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0|max:100',
            'quantity' => 'required|integer|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive,draft',
        ];
    }

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $this->name = $product->name;
            $this->sku = $product->sku;
            $this->barcode = $product->barcode ?? '';
            $this->description = $product->description ?? '';
            $this->category_id = $product->category_id;
            $this->supplier_id = $product->supplier_id;
            $this->media_id = $product->media_id;
            $this->cost_price = $product->cost_price;
            $this->selling_price = $product->selling_price;
            $this->tax_rate = $product->tax_rate;
            $this->discount = $product->discount;
            $this->discount_type = $product->discount_type ?? 'percentage';
            $this->quantity = $product->quantity;
            $this->min_stock_level = $product->min_stock_level;
            $this->unit = $product->unit;
            $this->weight = $product->weight ?? '';
            $this->dimensions = $product->dimensions ?? '';
            $this->status = $product->status;
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        // Clean empty strings to null
        $data['barcode'] = $data['barcode'] ?: null;
        $data['weight'] = $data['weight'] !== '' ? (float) $data['weight'] : null;
        $data['dimensions'] = $data['dimensions'] ?: null;
        $data['tax_rate'] = $data['tax_rate'] !== '' ? (float) $data['tax_rate'] : 0;
        $data['discount'] = $data['discount'] !== '' ? (float) $data['discount'] : 0;
        $data['cost_price'] = (float) $data['cost_price'];
        $data['selling_price'] = (float) $data['selling_price'];
        $data['quantity'] = (int) $data['quantity'];
        $data['min_stock_level'] = (int) $data['min_stock_level'];
        $data['slug'] = Str::slug($data['name']);

        if ($this->product?->exists) {
            $this->product->update($data);
            $message = 'Product updated successfully!';
        } else {
            Product::create($data);
            $message = 'Product created successfully!';
        }

        DashboardIndex::flushCache();

        session()->flash('success', $message);
        $this->redirect(route('products.index'), navigate: true);
    }

    public function selectMedia(int $mediaId): void
    {
        $this->media_id = $mediaId;
        $this->showMediaPicker = false;
    }

    public function removeMedia(): void
    {
        $this->media_id = null;
    }

    public function render()
    {
        $categories = Category::active()->ordered()->get();
        $suppliers = Supplier::active()->get();
        $media = $this->media_id ? Media::find($this->media_id) : null;

        return view('livewire.products.product-form', compact('categories', 'suppliers', 'media'))
            ->layout('layouts.app', ['title' => $this->product?->exists ? 'Edit Product' : 'Add Product']);
    }
}

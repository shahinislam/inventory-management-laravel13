<?php

namespace App\Livewire\Promotions;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Livewire\Component;

class PromotionForm extends Component
{
    public ?Promotion $promotion = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $type = 'percentage';

    public string $value = '';

    public string $max_discount = '';

    public string $min_order_amount = '';

    public string $min_quantity = '';

    public string $buy_quantity = '';

    public string $get_quantity = '';

    public string $applyTo = 'all'; // all, product, category

    public ?int $product_id = null;

    public ?int $category_id = null;

    public string $usage_limit = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:50|unique:promotions,code,'.($this->promotion?->id ?? 'NULL'),
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed,buy_x_get_y,bundle',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'buy_quantity' => 'nullable|integer|min:0',
            'get_quantity' => 'nullable|integer|min:0',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'usage_limit' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ];
    }

    public function mount(?Promotion $promotion = null): void
    {
        if ($promotion?->exists) {
            $this->promotion = $promotion;
            $this->name = $promotion->name;
            $this->code = $promotion->code ?? '';
            $this->description = $promotion->description ?? '';
            $this->type = $promotion->type;
            $this->value = $promotion->value;
            $this->max_discount = $promotion->max_discount ?? '';
            $this->min_order_amount = $promotion->min_order_amount ?? '';
            $this->min_quantity = $promotion->min_quantity ?? '';
            $this->buy_quantity = $promotion->buy_quantity ?? '';
            $this->get_quantity = $promotion->get_quantity ?? '';
            $this->product_id = $promotion->product_id;
            $this->category_id = $promotion->category_id;
            $this->applyTo = $promotion->product_id ? 'product' : ($promotion->category_id ? 'category' : 'all');
            $this->usage_limit = $promotion->usage_limit ?? '';
            $this->starts_at = $promotion->starts_at?->format('Y-m-d\TH:i') ?? '';
            $this->ends_at = $promotion->ends_at?->format('Y-m-d\TH:i') ?? '';
            $this->is_active = $promotion->is_active;
        }
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['code'] = $data['code'] ?: null;
        $data['description'] = $data['description'] ?: null;
        $data['max_discount'] = $data['max_discount'] !== '' ? (float) $data['max_discount'] : null;
        $data['min_order_amount'] = $data['min_order_amount'] !== '' ? (float) $data['min_order_amount'] : null;
        $data['min_quantity'] = $data['min_quantity'] !== '' ? (int) $data['min_quantity'] : null;
        $data['buy_quantity'] = $data['buy_quantity'] !== '' ? (int) $data['buy_quantity'] : null;
        $data['get_quantity'] = $data['get_quantity'] !== '' ? (int) $data['get_quantity'] : null;
        $data['usage_limit'] = $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null;
        $data['starts_at'] = $data['starts_at'] ?: null;
        $data['ends_at'] = $data['ends_at'] ?: null;

        // Clear the non-applicable target
        if ($this->applyTo !== 'product') {
            $data['product_id'] = null;
        }
        if ($this->applyTo !== 'category') {
            $data['category_id'] = null;
        }

        if ($this->promotion?->exists) {
            $this->promotion->update($data);
            $message = 'Promotion updated successfully!';
        } else {
            Promotion::create($data);
            $message = 'Promotion created successfully!';
        }

        session()->flash('success', $message);
        $this->redirect(route('promotions.index'), navigate: true);
    }

    public function render()
    {
        $products = Product::active()->get();
        $categories = Category::active()->get();

        return view('livewire.promotions.promotion-form', compact('products', 'categories'))
            ->layout('layouts.app', ['title' => $this->promotion?->exists ? 'Edit Promotion' : 'New Promotion']);
    }
}

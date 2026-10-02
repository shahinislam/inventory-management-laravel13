<?php

namespace App\Livewire\Products;

use App\Concerns\HandlesBarcodeScans;
use App\Models\Product;
use Livewire\Component;

class LabelPrint extends Component
{
    use HandlesBarcodeScans;

    public string $search = '';

    public string $size = '38x25';

    /** @var list<array{id:int, name:string, sku:?string, barcode:?string, unit:?string, price:float, loose:bool, copies:int, weight:?string}> */
    public array $items = [];

    public const SIZES = [
        '38x25' => '38 × 25 mm (thermal)',
        '50x30' => '50 × 30 mm (thermal)',
        'a4' => 'A4 sheet (3 × 8)',
    ];

    public function addProduct(int $id): void
    {
        $product = Product::find($id);

        if ($product) {
            $this->addToList($product);
        }

        $this->search = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function clearItems(): void
    {
        $this->items = [];
    }

    public function updatedItems(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key), 2, null);

        if (! isset($this->items[$index])) {
            return;
        }

        if ($field === 'copies') {
            $this->items[$index]['copies'] = max(1, min(500, (int) $value));
        }

        if ($field === 'weight') {
            $weight = (float) $value;
            $this->items[$index]['weight'] = $weight > 0 ? (string) $weight : null;
        }
    }

    public function updatedSize(string $value): void
    {
        if (! array_key_exists($value, self::SIZES)) {
            $this->size = '38x25';
        }
    }

    protected function handleScan(string $code): bool
    {
        $product = $this->findScannedProduct($code);

        if (! $product) {
            return false;
        }

        $this->addToList($product);

        return true;
    }

    /** Adds the product, or one more copy when it is already listed. */
    protected function addToList(Product $product): void
    {
        foreach ($this->items as $index => $item) {
            if ($item['id'] === $product->id) {
                $this->items[$index]['copies']++;

                return;
            }
        }

        $this->items[] = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit' => $product->unit,
            'price' => (float) $product->selling_price,
            'loose' => $product->isLoose(),
            'copies' => 1,
            'weight' => null,
        ];
    }

    public function printUrl(): ?string
    {
        if ($this->items === []) {
            return null;
        }

        $payload = collect($this->items)->map(fn (array $item) => array_filter([
            'id' => $item['id'],
            'copies' => max(1, (int) $item['copies']),
            'weight' => $item['loose'] && (float) $item['weight'] > 0 ? (float) $item['weight'] : null,
        ], fn ($v) => $v !== null))->values();

        return route('products.labels.print', [
            'items' => $payload->toJson(),
            'size' => $this->size,
        ]);
    }

    public function render()
    {
        $results = strlen(trim($this->search)) > 0
            ? Product::query()
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->limit(8)
                ->get()
            : collect();

        return view('livewire.products.label-print', [
            'results' => $results,
            'sizes' => self::SIZES,
            'printUrl' => $this->printUrl(),
            'totalLabels' => collect($this->items)->sum(fn ($i) => max(1, (int) $i['copies'])),
        ])->layout('layouts.app', ['title' => 'Print Labels']);
    }
}

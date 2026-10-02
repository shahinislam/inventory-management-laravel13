<?php

namespace App\Livewire\Stock;

use App\Concerns\HandlesBarcodeScans;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Stocktake: count what is on the shelf and correct the system to match.
 *
 * Nothing is saved until "Post count" — the counted figures live only in
 * this page. Rows left blank are not touched.
 */
class StockCount extends Component
{
    use HandlesBarcodeScans;

    public ?int $warehouse_id = null;

    public string $category_id = '';

    public bool $started = false;

    public string $filter = '';

    /** Keyed by product id: name, sku, unit, loose, system, cost, counted. */
    public array $rows = [];

    public function mount(): void
    {
        $this->warehouse_id = Warehouse::getDefault()?->id;
    }

    public function start(): void
    {
        $this->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'category_id' => 'nullable|exists:categories,id',
        ], [
            'warehouse_id.required' => 'Choose the warehouse you are counting.',
        ]);

        $stock = DB::table('product_warehouse')
            ->where('warehouse_id', $this->warehouse_id)
            ->pluck('quantity', 'product_id');

        $this->rows = Product::active()
            ->when($this->category_id, fn ($q) => $q->where('category_id', $this->category_id))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'cost_price'])
            ->mapWithKeys(fn (Product $p) => [$p->id => [
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit,
                'loose' => $p->isLoose(),
                'system' => round((float) ($stock[$p->id] ?? 0), 3),
                'cost' => (float) $p->cost_price,
                'counted' => '',
            ]])
            ->all();

        $this->started = true;
        $this->filter = '';
    }

    public function cancel(): void
    {
        $this->reset(['rows', 'started', 'filter']);
    }

    /** A scan counts one more of that product. Loose goods must be weighed. */
    protected function handleScan(string $code): bool
    {
        if (! $this->started) {
            $this->scanError = 'Start the count first, then scan.';

            return false;
        }

        $product = $this->findScannedProduct($code);

        if (! $product) {
            return false;
        }

        if (! isset($this->rows[$product->id])) {
            $this->scanError = "{$product->name} is not in this count (check the category filter).";

            return false;
        }

        if ($this->rows[$product->id]['loose']) {
            $this->scanError = "{$product->name} is sold by weight. Weigh it and type the amount.";

            return false;
        }

        $current = is_numeric($this->rows[$product->id]['counted']) ? (float) $this->rows[$product->id]['counted'] : 0;
        $this->rows[$product->id]['counted'] = (string) ($current + 1);

        return true;
    }

    /** Counted figure as a number, or null when the row was left blank. */
    private function counted(array $row): ?float
    {
        $value = trim((string) $row['counted']);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = max(0, (float) $value);

        return $row['loose'] ? round($value, 3) : floor($value);
    }

    public function post(InventoryService $inventory): void
    {
        abort_unless(auth()->user()->hasRole(['admin', 'manager']), 403);

        if (! $this->started) {
            return;
        }

        $warehouseId = (int) $this->warehouse_id;
        $ref = 'SC-'.now()->format('Ymd-His');
        $changed = 0;
        $value = 0.0;

        DB::transaction(function () use ($inventory, $warehouseId, $ref, &$changed, &$value) {
            foreach ($this->rows as $productId => $row) {
                $counted = $this->counted($row);
                if ($counted === null) {
                    continue;
                }

                // Compare with the live figure, not the one loaded at start —
                // a sale during the count must not be double-corrected.
                $system = $inventory->stockIn((int) $productId, $warehouseId);
                if (abs($counted - $system) < InventoryService::EPSILON) {
                    continue;
                }

                $inventory->setTo((int) $productId, $warehouseId, $counted, [
                    'notes' => 'Stock count '.$ref,
                    'type' => 'adjustment',
                ]);

                $changed++;
                $value += ($counted - $system) * $row['cost'];
            }
        });

        DashboardIndex::flushCache();

        $counted = collect($this->rows)->filter(fn ($r) => $this->counted($r) !== null)->count();

        session()->flash('success', $changed
            ? "Count {$ref} posted: {$counted} products counted, {$changed} corrected (".($value < 0 ? '-' : '+').money(abs($value)).' at cost).'
            : "Count {$ref} checked: {$counted} products counted, everything matched.");

        $this->cancel();
    }

    public function render()
    {
        $rows = collect($this->rows)->map(function ($row) {
            $counted = $this->counted($row);
            $diff = $counted === null ? null : round($counted - $row['system'], 3);

            return $row + [
                'difference' => $diff,
                'value' => $diff === null ? null : $diff * $row['cost'],
            ];
        });

        $summary = [
            'total' => $rows->count(),
            'counted' => $rows->whereNotNull('difference')->count(),
            'different' => $rows->filter(fn ($r) => $r['difference'] !== null && abs($r['difference']) >= InventoryService::EPSILON)->count(),
            'value' => $rows->sum(fn ($r) => $r['value'] ?? 0),
        ];

        $term = mb_strtolower(trim($this->filter));
        $visible = $term === ''
            ? $rows
            : $rows->filter(fn ($r) => str_contains(mb_strtolower($r['name']), $term)
                || str_contains(mb_strtolower((string) $r['sku']), $term));

        return view('livewire.stock.stock-count', [
            'visible' => $visible,
            'summary' => $summary,
            'warehouses' => Warehouse::active()->get(),
            'categories' => Category::active()->orderBy('name')->get(),
            'canPost' => auth()->user()->hasRole(['admin', 'manager']),
            'warehouseName' => Warehouse::find($this->warehouse_id)?->name,
            'categoryName' => $this->category_id ? Category::find($this->category_id)?->name : null,
        ])->layout('layouts.app', ['title' => 'Stock Count']);
    }
}

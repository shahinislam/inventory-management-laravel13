<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** Warehouse this product's stock should be placed in, if not the default. */
    protected ?Warehouse $warehouse = null;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'sku' => strtoupper(Str::random(10)),
            'barcode' => fake()->unique()->ean13(),
            'cost_price' => 100,
            'selling_price' => 200,
            'tax_rate' => 0,
            'discount' => 0,
            'discount_type' => 'percentage',
            'quantity' => 100,
            'min_stock_level' => 10,
            'unit' => 'pcs',
            'status' => 'active',
        ];
    }

    /**
     * Put the product's stock into a warehouse after it is created.
     *
     * Stock lives in product_warehouse; products.quantity is only the total.
     * Without this a factory-made product would have a total with no location,
     * so nothing could actually be sold from it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->quantity <= 0) {
                return;
            }

            $warehouse = $this->warehouse ?? Warehouse::getDefault() ?? Warehouse::factory()->default()->create();

            $product->warehouses()->syncWithoutDetaching([
                $warehouse->id => ['quantity' => $product->quantity],
            ]);
        });
    }

    /**
     * Place this product's stock in a specific warehouse.
     */
    public function inWarehouse(Warehouse $warehouse): static
    {
        $clone = clone $this;
        $clone->warehouse = $warehouse;

        return $clone;
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['quantity' => 5, 'min_stock_level' => 10]);
    }
}

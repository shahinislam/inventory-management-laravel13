<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PricingService;

beforeEach(function () {
    $this->pricing = app(PricingService::class);
});

it('falls back to the product percentage discount when no promotion applies', function () {
    $product = Product::factory()->create([
        'selling_price' => 200,
        'discount' => 10,
        'discount_type' => 'percentage',
    ]);

    $discount = $this->pricing->resolveDiscount($product);

    expect($discount->perUnit)->toBe(20.0)
        ->and($discount->fromPromotion())->toBeFalse()
        ->and($discount->promotionId)->toBeNull();
});

it('falls back to the product fixed discount when no promotion applies', function () {
    $product = Product::factory()->create([
        'selling_price' => 200,
        'discount' => 35,
        'discount_type' => 'fixed',
    ]);

    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(35.0);
});

it('applies a category-wide promotion', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'selling_price' => 200,
        'discount' => 10,
    ]);

    Promotion::factory()->percentage(25)->create([
        'name' => 'Category Sale',
        'category_id' => $category->id,
    ]);

    $discount = $this->pricing->resolveDiscount($product);

    // 25% of 200 — the promotion beats the product's own 10%.
    expect($discount->perUnit)->toBe(50.0)
        ->and($discount->label)->toBe('Category Sale')
        ->and($discount->fromPromotion())->toBeTrue();
});

it('prefers a product-specific promotion over a category-wide one', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'selling_price' => 200,
    ]);

    Promotion::factory()->percentage(25)->create([
        'name' => 'Category Sale',
        'category_id' => $category->id,
    ]);
    Promotion::factory()->fixed(75)->create([
        'name' => 'Product Sale',
        'product_id' => $product->id,
    ]);

    $discount = $this->pricing->resolveDiscount($product);

    expect($discount->perUnit)->toBe(75.0)
        ->and($discount->label)->toBe('Product Sale');
});

it('caps a promotion discount at max_discount', function () {
    $product = Product::factory()->create(['selling_price' => 200]);

    Promotion::factory()->percentage(50)->create([
        'product_id' => $product->id,
        'max_discount' => 30,
    ]);

    // 50% of 200 would be 100, capped to 30.
    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(30.0);
});

it('ignores an expired promotion', function () {
    $product = Product::factory()->create([
        'selling_price' => 200,
        'discount' => 10,
    ]);

    Promotion::factory()->percentage(50)->expired()->create([
        'product_id' => $product->id,
    ]);

    // Falls back to the product's own 10%.
    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(20.0);
});

it('ignores an inactive promotion', function () {
    $product = Product::factory()->create(['selling_price' => 200, 'discount' => 10]);

    Promotion::factory()->percentage(50)->create([
        'product_id' => $product->id,
        'is_active' => false,
    ]);

    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(20.0);
});

it('ignores a promotion that has hit its usage limit', function () {
    $product = Product::factory()->create(['selling_price' => 200, 'discount' => 10]);

    Promotion::factory()->percentage(50)->exhausted()->create([
        'product_id' => $product->id,
    ]);

    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(20.0);
});

it('still applies a promotion that has usage headroom left', function () {
    $product = Product::factory()->create(['selling_price' => 200]);

    Promotion::factory()->percentage(50)->create([
        'product_id' => $product->id,
        'usage_limit' => 5,
        'used_count' => 4,
    ]);

    expect($this->pricing->resolveDiscount($product)->perUnit)->toBe(100.0);
});

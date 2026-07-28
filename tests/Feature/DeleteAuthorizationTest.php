<?php

use App\Livewire\Categories\CategoryList;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Products\ProductList;
use App\Livewire\Promotions\PromotionList;
use App\Livewire\Suppliers\SupplierList;
use App\Livewire\Warehouses\WarehouseList;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

/**
 * Staff can reach the product and customer screens (role:admin,manager,staff),
 * so delete() must refuse them on its own — route middleware lets them in.
 */
it('blocks staff from deleting a product', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $product = Product::factory()->create();

    Livewire::test(ProductList::class)
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::find($product->id))->not->toBeNull();
});

it('blocks staff from deleting a customer', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $customer = Customer::factory()->create();

    Livewire::test(CustomerList::class)
        ->call('confirmDelete', $customer->id)
        ->call('delete');

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('blocks a viewer from deleting a product', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));

    $product = Product::factory()->create();

    Livewire::test(ProductList::class)
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::find($product->id))->not->toBeNull();
});

it('lets a manager delete a product', function () {
    $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));

    $product = Product::factory()->create();

    Livewire::test(ProductList::class)
        ->call('confirmDelete', $product->id)
        ->call('delete');

    expect(Product::find($product->id))->toBeNull();
});

it('lets an admin delete a customer', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    $customer = Customer::factory()->create();

    Livewire::test(CustomerList::class)
        ->call('confirmDelete', $customer->id)
        ->call('delete');

    expect(Customer::find($customer->id))->toBeNull();
});

it('guards deletes on the manager-only lists too', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $category = Category::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $promotion = Promotion::factory()->create();

    Livewire::test(CategoryList::class)->call('confirmDelete', $category->id)->call('delete');
    Livewire::test(SupplierList::class)->call('confirmDelete', $supplier->id)->call('delete');
    Livewire::test(WarehouseList::class)->call('confirmDelete', $warehouse->id)->call('delete');
    Livewire::test(PromotionList::class)->call('confirmDelete', $promotion->id)->call('delete');

    expect(Category::find($category->id))->not->toBeNull()
        ->and(Supplier::find($supplier->id))->not->toBeNull()
        ->and(Warehouse::find($warehouse->id))->not->toBeNull()
        ->and(Promotion::find($promotion->id))->not->toBeNull();
});

it('still refuses to delete the default warehouse for an admin', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

    $warehouse = Warehouse::factory()->default()->create();

    Livewire::test(WarehouseList::class)
        ->call('confirmDelete', $warehouse->id)
        ->call('delete');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});

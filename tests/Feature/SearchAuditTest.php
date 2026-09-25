<?php

/**
 * Exercises every search surface in the app against real data.
 *
 * Guards the class of bug where a product exists but is invisible to search
 * because its stock never made it into product_warehouse.
 */

use App\Livewire\Categories\CategoryList;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Invoices\InvoiceList;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Products\ProductList;
use App\Livewire\Promotions\PromotionList;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseList;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Stock\MovementList;
use App\Livewire\Suppliers\SupplierList;
use App\Livewire\Warehouses\WarehouseList;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);

    $this->product = Product::factory()->create([
        'name' => 'Teer Soyabean Oil',
        'sku' => 'TEER-SOY-5L',
        'barcode' => '8901234567890',
        'quantity' => 0,
    ]);

    // Stock must be in the pivot, not just on products.quantity — that is what
    // POS search filters on.
    app(InventoryService::class)->add(
        productId: $this->product->id,
        warehouseId: $this->warehouse->id,
        quantity: 50,
        type: 'adjustment',
    );

    $this->customer = Customer::factory()->create([
        'name' => 'Usama Rahman',
        'phone' => '01712345678',
        'is_active' => true,
    ]);
});

it('finds a stocked product in POS search by name, sku and barcode', function (string $term) {
    Livewire::test(PosTerminal::class)
        ->set('search', $term)
        ->tap(fn ($c) => expect($c->instance()->searchResults)->toHaveCount(1));
})->with(['Soyabean', 'TEER-SOY']);

it('auto-adds a product to the POS cart on an exact barcode scan', function () {
    // Scans arrive as the barcode-scanned event from the browser-side scanner
    // detector (resources/js/app.js), not through the search box.
    Livewire::test(PosTerminal::class)
        ->dispatch('barcode-scanned', code: '8901234567890')
        ->assertCount('cart', 1)
        ->assertSet('search', '');
});

it('hides a product with no stock in the selected warehouse from POS search', function () {
    Product::factory()->create(['name' => 'Ghost Item', 'quantity' => 0]);

    Livewire::test(PosTerminal::class)
        ->set('search', 'Ghost')
        ->tap(fn ($c) => expect($c->instance()->searchResults)->toHaveCount(0));
});

it('finds a customer in POS by name and by phone', function (string $term) {
    Livewire::test(PosTerminal::class)
        ->set('customerSearch', $term)
        ->tap(fn ($c) => expect($c->instance()->customerResults)->toHaveCount(1));
})->with(['Usama', '01712345678']);

it('finds a customer in the invoice form by name and by phone', function (string $term) {
    Livewire::test(InvoiceForm::class)
        ->set('customerSearch', $term)
        ->tap(fn ($c) => expect($c->instance()->customerResults)->toHaveCount(1));
})->with(['Usama', '01712345678']);

it('finds a product in the invoice form by name, sku and barcode', function (string $term) {
    Livewire::test(InvoiceForm::class)
        ->set('productSearch', $term)
        ->tap(fn ($c) => expect($c->instance()->productResults)->toHaveCount(1));
})->with(['Soyabean', 'TEER-SOY', '8901234567890']);

it('finds a product in the purchase form search', function () {
    Livewire::test(PurchaseForm::class)
        ->set('productSearch', 'Soyabean')
        ->tap(fn ($c) => expect($c->instance()->searchProducts())->not->toBeEmpty());
});

it('filters the product list by search term', function () {
    Product::factory()->create(['name' => 'Unrelated Thing']);

    Livewire::test(ProductList::class)
        ->set('search', 'Soyabean')
        ->assertSee('Teer Soyabean Oil')
        ->assertDontSee('Unrelated Thing');
});

it('filters the customer list by search term', function () {
    Customer::factory()->create(['name' => 'Someone Else']);

    Livewire::test(CustomerList::class)
        ->set('search', 'Usama')
        ->assertSee('Usama Rahman')
        ->assertDontSee('Someone Else');
});

it('filters the category list by search term', function () {
    Category::factory()->create(['name' => 'Grocery']);
    Category::factory()->create(['name' => 'Hardware']);

    Livewire::test(CategoryList::class)
        ->set('search', 'Grocery')
        ->assertSee('Grocery')
        ->assertDontSee('Hardware');
});

it('renders every list screen with a search term applied', function (string $component) {
    Livewire::test($component)
        ->set('search', 'zzz-no-match')
        ->assertOk();
})->with([
    ProductList::class,
    CustomerList::class,
    CategoryList::class,
    SupplierList::class,
    InvoiceList::class,
    PurchaseList::class,
    WarehouseList::class,
    UserManagement::class,
    MovementList::class,
    PromotionList::class,
]);

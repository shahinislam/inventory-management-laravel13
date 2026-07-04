<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public routes
Route::get('/', fn() => redirect()->route('dashboard'));

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', \App\Livewire\Dashboard\Index::class)->name('dashboard');

    // Products
    Route::prefix('products')->name('products.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', \App\Livewire\Products\ProductList::class)->name('index');
        Route::get('/create', \App\Livewire\Products\ProductForm::class)->name('create');
        Route::get('/{product}/edit', \App\Livewire\Products\ProductForm::class)->name('edit');
    });

    // Categories
    Route::prefix('categories')->name('categories.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', \App\Livewire\Categories\CategoryList::class)->name('index');
        Route::get('/create', \App\Livewire\Categories\CategoryForm::class)->name('create');
        Route::get('/{category}/edit', \App\Livewire\Categories\CategoryForm::class)->name('edit');
    });

    // Suppliers
    Route::prefix('suppliers')->name('suppliers.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', \App\Livewire\Suppliers\SupplierList::class)->name('index');
        Route::get('/create', \App\Livewire\Suppliers\SupplierForm::class)->name('create');
        Route::get('/{supplier}/edit', \App\Livewire\Suppliers\SupplierForm::class)->name('edit');
    });

    // Warehouses
    Route::prefix('warehouses')->name('warehouses.')->middleware('role:admin')->group(function () {
        Route::get('/', \App\Livewire\Warehouses\WarehouseList::class)->name('index');
        Route::get('/create', \App\Livewire\Warehouses\WarehouseForm::class)->name('create');
        Route::get('/{warehouse}/edit', \App\Livewire\Warehouses\WarehouseForm::class)->name('edit');
    });

    // Customers
    Route::prefix('customers')->name('customers.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', \App\Livewire\Customers\CustomerList::class)->name('index');
        Route::get('/create', \App\Livewire\Customers\CustomerForm::class)->name('create');
        Route::get('/{customer}/edit', \App\Livewire\Customers\CustomerForm::class)->name('edit');
    });

    // Stock Management
    Route::prefix('stock')->name('stock.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', \App\Livewire\Stock\MovementList::class)->name('index');
        Route::get('/adjust', \App\Livewire\Stock\StockAdjustment::class)->name('adjust');
        Route::get('/transfer', \App\Livewire\Stock\StockTransfer::class)->name('transfer');
    });

    // Purchase Orders
    Route::prefix('purchases')->name('purchases.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', \App\Livewire\Purchases\PurchaseList::class)->name('index');
        Route::get('/create', \App\Livewire\Purchases\PurchaseForm::class)->name('create');
        Route::get('/{purchaseOrder}/edit', \App\Livewire\Purchases\PurchaseForm::class)->name('edit');
    });

    // Invoices
    Route::prefix('invoices')->name('invoices.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', \App\Livewire\Invoices\InvoiceList::class)->name('index');
        Route::get('/create', \App\Livewire\Invoices\InvoiceForm::class)->name('create');
        Route::get('/{invoice}', \App\Livewire\Invoices\InvoiceView::class)->name('show');
        Route::get('/{invoice}/edit', \App\Livewire\Invoices\InvoiceForm::class)->name('edit');
    });

    // POS Terminal
    Route::get('/pos', \App\Livewire\Pos\PosTerminal::class)
        ->name('pos')
        ->middleware('role:admin,manager,staff');

    // Promotions
    Route::prefix('promotions')->name('promotions.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', \App\Livewire\Promotions\PromotionList::class)->name('index');
        Route::get('/create', \App\Livewire\Promotions\PromotionForm::class)->name('create');
        Route::get('/{promotion}/edit', \App\Livewire\Promotions\PromotionForm::class)->name('edit');
    });

    // Media Library
    Route::prefix('media')->name('media.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', \App\Livewire\Media\MediaLibrary::class)->name('index');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('role:admin,manager')->group(function () {
        Route::get('/stock', \App\Livewire\Reports\StockReport::class)->name('stock');
        Route::get('/sales', \App\Livewire\Reports\SalesReport::class)->name('sales');
        Route::get('/low-stock', \App\Livewire\Reports\LowStockAlert::class)->name('low-stock');
    });

    // Settings (Admin only)
    Route::prefix('settings')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/general', \App\Livewire\Settings\GeneralSettings::class)->name('general');
        Route::get('/users', \App\Livewire\Settings\UserManagement::class)->name('users');
        Route::get('/roles', \App\Livewire\Settings\RoleManagement::class)->name('roles');
    });
});

require __DIR__.'/settings.php';

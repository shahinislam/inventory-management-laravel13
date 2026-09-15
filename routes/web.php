<?php

use App\Livewire\Categories\CategoryForm;
use App\Livewire\Categories\CategoryList;
use App\Livewire\Customers\CustomerForm;
use App\Livewire\Customers\CustomerList;
use App\Livewire\Dashboard\Index;
use App\Livewire\Invoices\InvoiceForm;
use App\Livewire\Invoices\InvoiceList;
use App\Livewire\Invoices\InvoiceView;
use App\Livewire\Media\MediaLibrary;
use App\Livewire\Partners\PartnerForm;
use App\Livewire\Partners\PartnerList;
use App\Livewire\Partners\PartnershipReport;
use App\Livewire\Partners\PartnerTransactions;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductList;
use App\Livewire\Promotions\PromotionForm;
use App\Livewire\Promotions\PromotionList;
use App\Livewire\Purchases\PurchaseForm;
use App\Livewire\Purchases\PurchaseList;
use App\Livewire\Reports\LowStockAlert;
use App\Livewire\Reports\SalesReport;
use App\Livewire\Reports\StockReport;
use App\Livewire\Settings\GeneralSettings;
use App\Livewire\Settings\RoleManagement;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Stock\MovementList;
use App\Livewire\Stock\StockAdjustment;
use App\Livewire\Stock\StockTransfer;
use App\Livewire\Suppliers\SupplierForm;
use App\Livewire\Suppliers\SupplierList;
use App\Livewire\Warehouses\WarehouseForm;
use App\Livewire\Warehouses\WarehouseList;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', Index::class)->name('dashboard');

    // Products — viewers may browse the catalogue but not change it.
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', ProductList::class)
            ->middleware('role:admin,manager,staff,viewer')->name('index');

        Route::middleware('role:admin,manager,staff')->group(function () {
            Route::get('/create', ProductForm::class)->name('create');
            Route::get('/{product}/edit', ProductForm::class)->name('edit');
        });
    });

    // Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', CategoryList::class)
            ->middleware('role:admin,manager,viewer')->name('index');

        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/create', CategoryForm::class)->name('create');
            Route::get('/{category}/edit', CategoryForm::class)->name('edit');
        });
    });

    // Suppliers
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', SupplierList::class)
            ->middleware('role:admin,manager,viewer')->name('index');

        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/create', SupplierForm::class)->name('create');
            Route::get('/{supplier}/edit', SupplierForm::class)->name('edit');
        });
    });

    // Warehouses
    Route::prefix('warehouses')->name('warehouses.')->middleware('role:admin')->group(function () {
        Route::get('/', WarehouseList::class)->name('index');
        Route::get('/create', WarehouseForm::class)->name('create');
        Route::get('/{warehouse}/edit', WarehouseForm::class)->name('edit');
    });

    // Customers
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', CustomerList::class)
            ->middleware('role:admin,manager,staff,viewer')->name('index');

        Route::middleware('role:admin,manager,staff')->group(function () {
            Route::get('/create', CustomerForm::class)->name('create');
            Route::get('/{customer}/edit', CustomerForm::class)->name('edit');
        });
    });

    // Stock Management — the movement log is read-only, adjustments are not.
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', MovementList::class)
            ->middleware('role:admin,manager,staff,viewer')->name('index');

        Route::middleware('role:admin,manager,staff')->group(function () {
            Route::get('/adjust', StockAdjustment::class)->name('adjust');
            Route::get('/transfer', StockTransfer::class)->name('transfer');
        });
    });

    // Purchase Orders
    Route::prefix('purchases')->name('purchases.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', PurchaseList::class)->name('index');
        Route::get('/create', PurchaseForm::class)->name('create');
        Route::get('/{purchaseOrder}/edit', PurchaseForm::class)->name('edit');
    });

    // Invoices — viewers may read invoices but not create or edit them.
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::middleware('role:admin,manager,staff')->group(function () {
            Route::get('/create', InvoiceForm::class)->name('create');
            Route::get('/{invoice}/edit', InvoiceForm::class)->name('edit');
        });

        // Declared after /create so the literal segment wins the match.
        Route::middleware('role:admin,manager,staff,viewer')->group(function () {
            Route::get('/', InvoiceList::class)->name('index');

            // 80mm thermal receipt — a bare page that auto-opens the print
            // dialog, so it must be declared before the catch-all /{invoice}.
            Route::get('/{invoice}/receipt', function (Invoice $invoice) {
                return view('pdf.receipt', [
                    'invoice' => $invoice->load(['items', 'warehouse', 'createdBy', 'payments']),
                    'company' => Setting::getGroup('company'),
                ]);
            })->name('receipt');

            Route::get('/{invoice}', InvoiceView::class)->name('show');
        });
    });

    // POS Terminal
    Route::get('/pos', PosTerminal::class)
        ->name('pos')
        ->middleware('role:admin,manager,staff');

    // Promotions
    Route::prefix('promotions')->name('promotions.')->middleware('role:admin,manager')->group(function () {
        Route::get('/', PromotionList::class)->name('index');
        Route::get('/create', PromotionForm::class)->name('create');
        Route::get('/{promotion}/edit', PromotionForm::class)->name('edit');
    });

    // Media Library
    Route::prefix('media')->name('media.')->middleware('role:admin,manager,staff')->group(function () {
        Route::get('/', MediaLibrary::class)->name('index');
    });

    // Reports — read-only by nature, so viewers are included.
    Route::prefix('reports')->name('reports.')->middleware('role:admin,manager,viewer')->group(function () {
        Route::get('/stock', StockReport::class)->name('stock');
        Route::get('/sales', SalesReport::class)->name('sales');
        Route::get('/low-stock', LowStockAlert::class)->name('low-stock');
    });

    // Partnership (Admin only) — investment and profit figures are sensitive.
    // Literal segments are declared before the /{partner} catch-alls.
    Route::prefix('partners')->name('partners.')->middleware('role:admin')->group(function () {
        Route::get('/', PartnerList::class)->name('index');
        Route::get('/report', PartnershipReport::class)->name('report');
        Route::get('/transactions', PartnerTransactions::class)->name('transactions');
        Route::get('/create', PartnerForm::class)->name('create');
        Route::get('/{partner}/edit', PartnerForm::class)->name('edit');
    });

    // Settings (Admin only)
    Route::prefix('settings')->name('settings.')->middleware('role:admin')->group(function () {
        Route::get('/general', GeneralSettings::class)->name('general');
        Route::get('/users', UserManagement::class)->name('users');
        Route::get('/roles', RoleManagement::class)->name('roles');
    });
});

require __DIR__.'/settings.php';

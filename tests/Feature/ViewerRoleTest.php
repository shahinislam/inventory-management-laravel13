<?php

use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
    $this->actingAs($this->viewer);
});

it('lets a viewer reach the read-only screens', function (string $route) {
    $this->get(route($route))->assertOk();
})->with([
    'dashboard',
    'products.index',
    'categories.index',
    'suppliers.index',
    'customers.index',
    'stock.index',
    'invoices.index',
    'reports.stock',
    'reports.sales',
    'reports.low-stock',
]);

it('blocks a viewer from screens that create or change data', function (string $route) {
    $this->get(route($route))->assertForbidden();
})->with([
    'products.create',
    'categories.create',
    'suppliers.create',
    'customers.create',
    'invoices.create',
    'stock.adjust',
    'stock.transfer',
    'pos',
    'purchases.index',
    'promotions.index',
    'warehouses.index',
    'media.index',
    'settings.general',
    'settings.users',
]);

it('lets a viewer open a single invoice but not edit it', function () {
    $invoice = Invoice::create([
        'created_by' => $this->viewer->id,
        'customer_name' => 'Test',
        'status' => 'sent',
        'subtotal' => 100,
        'tax' => 0,
        'discount' => 0,
        'total' => 100,
        'paid_amount' => 0,
        'due_amount' => 100,
        'invoice_date' => now(),
    ]);

    $this->get(route('invoices.show', $invoice))->assertOk();
    $this->get(route('invoices.edit', $invoice))->assertForbidden();
});

it('still lets staff reach the screens they had before', function (string $route) {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $this->get(route($route))->assertOk();
})->with([
    'products.index',
    'products.create',
    'customers.index',
    'customers.create',
    'stock.index',
    'stock.adjust',
    'stock.transfer',
    'invoices.index',
    'invoices.create',
    'pos',
    'media.index',
]);

it('still blocks staff from manager-only screens', function (string $route) {
    $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));

    $this->get(route($route))->assertForbidden();
})->with([
    'categories.index',
    'suppliers.index',
    'purchases.index',
    'promotions.index',
    'warehouses.index',
    'reports.stock',
    'settings.general',
]);

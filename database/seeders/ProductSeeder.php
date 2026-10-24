<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $grocery = Category::updateOrCreate(['slug' => 'grocery'], ['name' => 'Grocery',       'is_active' => true, 'order' => 1]);
        $beverages = Category::updateOrCreate(['slug' => 'beverages'], ['name' => 'Beverages',     'is_active' => true, 'order' => 2]);
        $dairy = Category::updateOrCreate(['slug' => 'dairy'], ['name' => 'Dairy',         'is_active' => true, 'order' => 3]);
        $personal = Category::updateOrCreate(['slug' => 'personal-care'], ['name' => 'Personal Care', 'is_active' => true, 'order' => 4]);
        $snacks = Category::updateOrCreate(['slug' => 'snacks'], ['name' => 'Snacks',        'is_active' => true, 'order' => 5]);

        $supplier = Supplier::first();

        $products = [
            [
                'name' => 'Sunflower Oil (5L)',
                'sku' => 'OIL-SUN-5L',
                'barcode' => '8901234567890',
                'category_id' => $grocery->id,
                'cost_price' => 11.50,
                'selling_price' => 13.99,
                'tax_rate' => 5.00,
                'discount' => 0.50,
                'discount_type' => 'fixed',
                'quantity' => 150,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Turmeric Powder (200g)',
                'sku' => 'SPICE-TUR-200G',
                'barcode' => '8901234567891',
                'category_id' => $grocery->id,
                'cost_price' => 1.60,
                'selling_price' => 2.49,
                'tax_rate' => 0,
                'discount' => 5.00,
                'discount_type' => 'percentage',
                'quantity' => 300,
                'min_stock_level' => 50,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Mango Juice (250ml)',
                'sku' => 'JUICE-MNG-250ML',
                'barcode' => '8901234567892',
                'category_id' => $beverages->id,
                'cost_price' => 0.55,
                'selling_price' => 0.99,
                'tax_rate' => 5.00,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 500,
                'min_stock_level' => 100,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Aloe Vera Soap (100g)',
                'sku' => 'SOAP-ALOE-100G',
                'barcode' => '8901234567893',
                'category_id' => $personal->id,
                'cost_price' => 0.70,
                'selling_price' => 1.25,
                'tax_rate' => 0,
                'discount' => 0.10,
                'discount_type' => 'fixed',
                'quantity' => 400,
                'min_stock_level' => 60,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Full Cream Milk (1L)',
                'sku' => 'MILK-FC-1L',
                'barcode' => '8901234567894',
                'category_id' => $dairy->id,
                'cost_price' => 0.95,
                'selling_price' => 1.49,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 200,
                'min_stock_level' => 30,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Whole Wheat Flour (2kg)',
                'sku' => 'FLOUR-WW-2KG',
                'barcode' => '8901234567895',
                'category_id' => $grocery->id,
                'cost_price' => 1.80,
                'selling_price' => 2.79,
                'tax_rate' => 0,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'quantity' => 250,
                'min_stock_level' => 40,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Spicy Trail Mix (400g)',
                'sku' => 'SNACK-MIX-400G',
                'barcode' => '8901234567896',
                'category_id' => $snacks->id,
                'cost_price' => 2.20,
                'selling_price' => 3.49,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 180,
                'min_stock_level' => 30,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Instant Coffee (50g)',
                'sku' => 'COFFEE-INST-50G',
                'barcode' => '8901234567897',
                'category_id' => $beverages->id,
                'cost_price' => 2.80,
                'selling_price' => 4.29,
                'tax_rate' => 5.00,
                'discount' => 0.30,
                'discount_type' => 'fixed',
                'quantity' => 120,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Herbal Shampoo (340ml)',
                'sku' => 'SHAMPOO-HRB-340ML',
                'barcode' => '8901234567898',
                'category_id' => $personal->id,
                'cost_price' => 3.10,
                'selling_price' => 4.99,
                'tax_rate' => 0,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'quantity' => 15,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Sweetened Condensed Milk (400g)',
                'sku' => 'MILK-COND-400G',
                'barcode' => '8901234567899',
                'category_id' => $dairy->id,
                'cost_price' => 1.30,
                'selling_price' => 1.99,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 0,
                'min_stock_level' => 25,
                'unit' => 'pcs',
            ],
            // Loose goods, sold by weight: the POS asks for the kg on the scale.
            [
                'name' => 'Long Grain Rice (loose)',
                'sku' => 'RICE-LG-KG',
                'barcode' => '2000000000015',
                'category_id' => $grocery->id,
                'cost_price' => 1.20,
                'selling_price' => 1.79,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 250.5,
                'min_stock_level' => 50,
                'unit' => 'kg',
            ],
            [
                'name' => 'Potato (loose)',
                'sku' => 'VEG-POTATO-KG',
                'barcode' => '2000000000022',
                'category_id' => $grocery->id,
                'cost_price' => 0.60,
                'selling_price' => 0.99,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 120,
                'min_stock_level' => 20,
                'unit' => 'kg',
            ],
            [
                'name' => 'Olive Oil (loose)',
                'sku' => 'OIL-OLIVE-LTR',
                'barcode' => '2000000000039',
                'category_id' => $grocery->id,
                'cost_price' => 6.50,
                'selling_price' => 8.99,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 40,
                'min_stock_level' => 10,
                'unit' => 'ltr',
            ],
        ];

        // Stock lives in product_warehouse, not on products.quantity — the POS
        // and every stock query read the pivot. Seeding the column alone leaves
        // products invisible to product search, so put the opening stock into
        // the default warehouse through the same service the app uses.
        $warehouse = Warehouse::getDefault() ?? Warehouse::first();
        $inventory = app(InventoryService::class);

        // Stock movements are attributed to the acting user, and seeding has no
        // authenticated session. Act as the admin so the audit trail is valid.
        if ($admin = User::where('role', 'admin')->first()) {
            auth()->setUser($admin);
        }

        foreach ($products as $product) {
            $record = Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'slug' => Str::slug($product['name']),
                    'supplier_id' => $supplier?->id,
                    'status' => 'active',
                ])
            );

            if (! $warehouse || $product['quantity'] <= 0) {
                continue;
            }

            // Re-running the seeder must not keep stacking stock on top.
            $onHand = $inventory->stockIn($record->id, $warehouse->id);
            $shortfall = round((float) $product['quantity'] - $onHand, 3);

            if ($shortfall > 0) {
                $inventory->add(
                    productId: $record->id,
                    warehouseId: $warehouse->id,
                    quantity: $shortfall,
                    type: 'adjustment',
                    extra: ['notes' => 'Opening stock (seed)'],
                );
            }
        }
    }
}

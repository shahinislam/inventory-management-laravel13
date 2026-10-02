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
                'name' => 'Teer Soyabean Oil (5L)',
                'sku' => 'TEER-SOY-5L',
                'barcode' => '8901234567890',
                'category_id' => $grocery->id,
                'cost_price' => 750.00,
                'selling_price' => 820.00,
                'tax_rate' => 5.00,
                'discount' => 20.00,
                'discount_type' => 'fixed',
                'quantity' => 150,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Radhuni Turmeric Powder (200g)',
                'sku' => 'RADH-TUR-200G',
                'barcode' => '8901234567891',
                'category_id' => $grocery->id,
                'cost_price' => 55.00,
                'selling_price' => 65.00,
                'tax_rate' => 0,
                'discount' => 5.00,
                'discount_type' => 'percentage',
                'quantity' => 300,
                'min_stock_level' => 50,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Pran Mango Juice (250ml)',
                'sku' => 'PRAN-MNG-250ML',
                'barcode' => '8901234567892',
                'category_id' => $beverages->id,
                'cost_price' => 25.00,
                'selling_price' => 30.00,
                'tax_rate' => 5.00,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 500,
                'min_stock_level' => 100,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Meril Splash Soap (100g)',
                'sku' => 'MERI-SOP-100G',
                'barcode' => '8901234567893',
                'category_id' => $personal->id,
                'cost_price' => 28.00,
                'selling_price' => 35.00,
                'tax_rate' => 0,
                'discount' => 5.00,
                'discount_type' => 'fixed',
                'quantity' => 400,
                'min_stock_level' => 60,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Milk Vita Full Cream Milk (1L)',
                'sku' => 'MLKV-FCM-1L',
                'barcode' => '8901234567894',
                'category_id' => $dairy->id,
                'cost_price' => 75.00,
                'selling_price' => 85.00,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 200,
                'min_stock_level' => 30,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Fresh Atta (2kg)',
                'sku' => 'FRSH-ATT-2KG',
                'barcode' => '8901234567895',
                'category_id' => $grocery->id,
                'cost_price' => 90.00,
                'selling_price' => 105.00,
                'tax_rate' => 0,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'quantity' => 250,
                'min_stock_level' => 40,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Bombay Sweets Chanachur (400g)',
                'sku' => 'BOMB-CHA-400G',
                'barcode' => '8901234567896',
                'category_id' => $snacks->id,
                'cost_price' => 55.00,
                'selling_price' => 65.00,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 180,
                'min_stock_level' => 30,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Nescafe Classic (50g)',
                'sku' => 'NESC-CLS-50G',
                'barcode' => '8901234567897',
                'category_id' => $beverages->id,
                'cost_price' => 180.00,
                'selling_price' => 210.00,
                'tax_rate' => 5.00,
                'discount' => 15.00,
                'discount_type' => 'fixed',
                'quantity' => 120,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Keya Herbal Shampoo (340ml)',
                'sku' => 'KEYA-SHP-340ML',
                'barcode' => '8901234567898',
                'category_id' => $personal->id,
                'cost_price' => 130.00,
                'selling_price' => 150.00,
                'tax_rate' => 0,
                'discount' => 10.00,
                'discount_type' => 'percentage',
                'quantity' => 15,
                'min_stock_level' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Danish Condensed Milk (400g)',
                'sku' => 'DANS-CDM-400G',
                'barcode' => '8901234567899',
                'category_id' => $dairy->id,
                'cost_price' => 85.00,
                'selling_price' => 95.00,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 0,
                'min_stock_level' => 25,
                'unit' => 'pcs',
            ],
            // Loose goods, sold by weight: the POS asks for the kg on the scale.
            [
                'name' => 'Miniket Rice (loose)',
                'sku' => 'RICE-MINIKET-KG',
                'barcode' => '2000000000015',
                'category_id' => $grocery->id,
                'cost_price' => 68.00,
                'selling_price' => 76.00,
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
                'cost_price' => 38.00,
                'selling_price' => 45.00,
                'tax_rate' => 0,
                'discount' => 0,
                'discount_type' => 'percentage',
                'quantity' => 120,
                'min_stock_level' => 20,
                'unit' => 'kg',
            ],
            [
                'name' => 'Mustard Oil (loose)',
                'sku' => 'OIL-MUSTARD-LTR',
                'barcode' => '2000000000039',
                'category_id' => $grocery->id,
                'cost_price' => 230.00,
                'selling_price' => 260.00,
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

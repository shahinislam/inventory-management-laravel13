<?php

use App\Livewire\Products\LabelPrint;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Barcode\Code128;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->warehouse = Warehouse::factory()->default()->create();
    $this->actingAs($this->user);
});

it('renders a Code 128 svg whose bars depend on the data', function () {
    $short = Code128::svg('A1');
    $long = Code128::svg('HELLO-WORLD-123');

    expect($short)->toContain('<svg')->toContain('</svg>')
        ->and(substr_count($long, '<rect'))->toBeGreaterThan(substr_count($short, '<rect'))
        ->and(Code128::svg('ABC', 40, 1.5, true))->toContain('<text')->toContain('ABC');

    // start + 2 data + checksum + stop = 5 symbols: 3 bars each, stop has 4.
    expect(substr_count($short, '<rect'))->toBe(4 * 3 + 4);
});

it('encodes with a correct Code 128 checksum and uses subset C for long digit runs', function () {
    // Start B (104) + P J J 1 2 3 C, checksum = 879 mod 103 = 55, stop 106.
    expect(Code128::encode('PJJ123C'))->toBe([104, 48, 42, 42, 17, 18, 19, 35, 55, 106])
        ->and(Code128::encode('123456')[0])->toBe(105);
});

it('computes EAN-13 check digits and in-store codes', function () {
    expect(Code128::ean13CheckDigit('400638133393'))->toBe(1);

    $code = Code128::internalEan13(4521);

    expect($code)->toStartWith('2')
        ->toHaveLength(13)
        ->toMatch('/^\d{13}$/')
        ->and((int) substr($code, -1))->toBe(Code128::ean13CheckDigit(substr($code, 0, 12)));
});

it('shows the label print page', function () {
    $this->get(route('products.labels'))->assertOk()->assertSee('Print price labels');
});

it('adds products by search and scan and builds a print link', function () {
    $product = Product::factory()->create(['name' => 'Basmati Rice', 'unit' => 'kg', 'barcode' => null, 'selling_price' => 120]);

    Livewire::test(LabelPrint::class)
        ->call('addProduct', $product->id)
        ->assertCount('items', 1)
        ->assertSee('No barcode — SKU will be printed')
        ->call('onBarcodeScanned', $product->sku)
        ->assertSet('items.0.copies', 2)
        ->set('items.0.weight', '0.5')
        ->assertSee(money(60))
        ->call('onBarcodeScanned', 'NOPE-404')
        ->assertDispatched('scan-result', found: false)
        ->assertSee('products/labels/print', false);
});

it('prints one label per copy', function () {
    $product = Product::factory()->create(['name' => 'Golden Tea Pack']);

    $response = $this->get(route('products.labels.print', [
        'items' => json_encode([['id' => $product->id, 'copies' => 2]]),
        'size' => '38x25',
    ]));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'Golden Tea Pack'))->toBe(2)
        ->and($response->getContent())->toContain('<svg');
});

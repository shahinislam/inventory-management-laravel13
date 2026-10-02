<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('stock.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">Stock Transfer</flux:heading>
            <flux:text class="mt-1">Move inventory between warehouses</flux:text>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">

        {{-- Left: Form --}}
        <div>
            <form wire:submit="save">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Transfer Details</flux:heading>

                    <div class="space-y-4">

                        {{-- Product Search --}}
                        <flux:field>
                            <flux:label badge="Required">Product</flux:label>
                            <x-search-select model="search" autofocus
                                placeholder="Search by name, SKU or scan barcode..."
                                :show="$products->count() > 0"
                                :selected="$selectedProduct?->name"
                                :selected-hint="$selectedProduct?->sku"
                                clear="clearProduct">
                                @foreach($products as $i => $product)
                                    <x-search-select.option :index="$i"
                                        wire:click="selectProduct({{ $product->id }})"
                                        wire:key="prod-{{ $product->id }}"
                                        :label="$product->name"
                                        :description="$product->sku"
                                        :value="format_qty($product->quantity, $product->unit)" />
                                @endforeach
                            </x-search-select>
                            <flux:error name="product_id" />
                        </flux:field>

                        {{-- Current Stock --}}
                        @if($selectedProduct)
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <flux:text class="text-sm text-zinc-500">Available Stock</flux:text>
                                <flux:heading size="lg">{{ format_qty($selectedProduct->quantity, $selectedProduct->unit) }}</flux:heading>
                            </div>
                        @endif

                        {{-- Warehouses --}}
                        <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                            <flux:field>
                                <flux:label badge="Required">From</flux:label>
                                <flux:select wire:model="from_warehouse_id">
                                    @foreach($warehouses as $warehouse)
                                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="from_warehouse_id" />
                            </flux:field>

                            {{-- Points down when the row stacks on mobile. --}}
                            <flux:icon name="arrow-right"
                                class="mx-auto size-5 rotate-90 text-zinc-400 sm:mb-2 sm:rotate-0" />

                            <flux:field>
                                <flux:label>To <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:select wire:model="to_warehouse_id" placeholder="Select destination">
                                    <flux:select.option value="">Select destination</flux:select.option>
                                    @foreach($warehouses as $warehouse)
                                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="to_warehouse_id" />
                            </flux:field>
                        </div>

                        {{-- Quantity --}}
                        <flux:field>
                            <flux:label>Quantity to Transfer <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input
                                wire:model="quantity"
                                type="number"
                                min="1"
                                max="{{ $selectedProduct && $from_warehouse_id ? $selectedProduct->stockIn($from_warehouse_id) : '' }}" step="{{ $selectedProduct?->isLoose() ? '0.001' : '1' }}"
                                placeholder="0"
                            />
                            <flux:error name="quantity" />
                        </flux:field>

                        {{-- Notes --}}
                        <flux:field>
                            <flux:label>Notes</flux:label>
                            <flux:textarea wire:model="notes" placeholder="Optional transfer notes..." rows="2" />
                            <flux:error name="notes" />
                        </flux:field>

                    </div>
                </flux:card>

                <div class="mt-4 flex gap-3">
                    <flux:button type="submit" icon="arrows-right-left" :disabled="!$selectedProduct">Transfer Stock</flux:button>
                    <flux:button type="button" variant="ghost" href="{{ route('stock.index') }}" wire:navigate>Cancel</flux:button>
                </div>
            </form>
        </div>

        {{-- Right: Info --}}
        <div class="space-y-6">
            <flux:card class="p-6">
                <flux:heading class="mb-2">ℹ️ About Transfers</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Stock transfers move inventory between warehouse locations without
                    changing the total quantity. Two movement records are created:
                    one "Transfer Out" from source and one "Transfer In" to destination.
                </flux:text>
            </flux:card>
        </div>

    </div>

</div>
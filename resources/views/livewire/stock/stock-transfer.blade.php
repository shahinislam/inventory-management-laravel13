<div class="p-6">

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

    <div style="display:grid;grid-template-columns:1fr 350px;gap:1.5rem">

        {{-- Left: Form --}}
        <div>
            <form wire:submit="save">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Transfer Details</flux:heading>

                    <div class="space-y-4">

                        {{-- Product Search --}}
                        <flux:field>
                            <flux:label>Product <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <div
                                style="position:relative"
                                x-data="{
                                    open: true,
                                    highlight: 0,
                                    count: {{ $products->count() }},
                                    moveDown() { if (this.count > 0) this.highlight = (this.highlight + 1) % this.count },
                                    moveUp() { if (this.count > 0) this.highlight = (this.highlight - 1 + this.count) % this.count },
                                }"
                                x-on:click.outside="open = false"
                                x-effect="count = {{ $products->count() }}; highlight = 0"
                            >
                                <flux:input
                                    wire:model.live.debounce.150ms="search"
                                    placeholder="Search by name, SKU or scan barcode..."
                                    icon="magnifying-glass"
                                    autofocus
                                    x-on:focus="open = true"
                                    x-on:keydown.arrow-down.prevent="moveDown()"
                                    x-on:keydown.arrow-up.prevent="moveUp()"
                                    x-on:keydown.enter.prevent="open = false; $wire.selectHighlighted(highlight)"
                                />
                                @if($selectedProduct)
                                    <flux:button
                                        icon="x-mark" variant="ghost" size="sm" square
                                        class="absolute right-2 top-1/2 -translate-y-1/2"
                                        wire:click="clearProduct" type="button"
                                    />
                                @endif

                                @if($products->count() > 0 && !$selectedProduct)
                                    <div x-show="open" class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        @foreach($products as $i => $product)
                                            <button
                                                type="button"
                                                wire:click="selectProduct({{ $product->id }})"
                                                x-on:click="open = false"
                                                x-on:mouseenter="highlight = {{ $i }}"
                                                wire:key="prod-{{ $product->id }}"
                                                class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors"
                                                x-bind:style="highlight === {{ $i }} ? 'background-color: rgb(37 99 235 / 0.15)' : ''"
                                            >
                                                <div>
                                                    <flux:text x-bind:class="highlight === {{ $i }} ? 'text-blue-700 dark:text-blue-300' : ''" class="text-sm font-medium">{{ $product->name }}</flux:text>
                                                    <flux:text class="text-xs text-zinc-400">{{ $product->sku }}</flux:text>
                                                </div>
                                                <flux:badge size="sm" color="zinc">{{ $product->quantity }} {{ $product->unit }}</flux:badge>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <flux:error name="product_id" />
                        </flux:field>

                        {{-- Current Stock --}}
                        @if($selectedProduct)
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <flux:text class="text-sm text-zinc-500">Available Stock</flux:text>
                                <flux:heading size="lg">{{ $selectedProduct->quantity }} {{ $selectedProduct->unit }}</flux:heading>
                            </div>
                        @endif

                        {{-- Warehouses --}}
                        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:1rem;align-items:end">
                            <flux:field>
                                <flux:label>From <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:select wire:model="from_warehouse_id">
                                    @foreach($warehouses as $warehouse)
                                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="from_warehouse_id" />
                            </flux:field>

                            <flux:icon name="arrow-right" class="size-5 text-zinc-400 mb-2" />

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
                                max="{{ $selectedProduct?->quantity }}"
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
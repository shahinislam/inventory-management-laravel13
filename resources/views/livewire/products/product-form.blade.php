<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('products.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->product?->exists ? 'Edit Product' : 'Add Product' }}</flux:heading>
            <flux:text class="mt-1">{{ $this->product?->exists ? 'Update product details' : 'Create a new product' }}</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Left Column (Main Info) --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Basic Information --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Basic Information</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Product Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="name" placeholder="Enter product name" />
                            <flux:error name="name" />
                        </flux:field>

                        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>SKU</flux:label>
                                <flux:input wire:model="sku" placeholder="Auto-generated if empty" />
                                <flux:error name="sku" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Barcode <flux:badge color="zinc" size="sm">Optional</flux:badge></flux:label>
                                <flux:input wire:model="barcode" placeholder="Scan or enter barcode" />
                                <flux:error name="barcode" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="description" placeholder="Product description..." rows="3" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Pricing --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Pricing</flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Cost Price <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="cost_price" type="number" step="0.01" min="0" placeholder="0.00" prefix="৳" />
                            <flux:error name="cost_price" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Selling Price <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="selling_price" type="number" step="0.01" min="0" placeholder="0.00" prefix="৳" />
                            <flux:error name="selling_price" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tax Rate (%)</flux:label>
                            <flux:input wire:model="tax_rate" type="number" step="0.01" min="0" max="100" placeholder="0" suffix="%" />
                            <flux:error name="tax_rate" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Discount</flux:label>
                            <div class="flex gap-2">
                                <flux:input wire:model="discount" type="number" step="0.01" min="0" placeholder="0" class="flex-1 tabular-nums" />
                                <flux:select wire:model="discount_type" class="w-28 shrink-0">
                                    <flux:select.option value="percentage">% Off</flux:select.option>
                                    <flux:select.option value="fixed">৳ Flat</flux:select.option>
                                </flux:select>
                            </div>
                            <flux:error name="discount" />
                        </flux:field>
                    </div>

                    {{-- Price Preview --}}
                    @if($selling_price > 0)
                    <div class="mt-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <flux:text class="text-sm">
                            Final Price:
                            <strong>
                                {{ money($selling_price - ($selling_price * $discount / 100) + (($selling_price - ($selling_price * $discount / 100)) * $tax_rate / 100)) }}
                            </strong>
                            (after {{ $discount }}% discount + {{ $tax_rate }}% tax)
                        </flux:text>
                    </div>
                    @endif
                </flux:card>

                {{-- Stock --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Stock Management</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Current Quantity <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="quantity" type="number" min="0" placeholder="0" />
                            <flux:error name="quantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Min Stock Level <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="min_stock_level" type="number" min="0" placeholder="0" />
                            <flux:error name="min_stock_level" />
                        </flux:field>

                        <flux:field class="col-span-full">
                            <flux:label>Unit</flux:label>
                            <flux:select wire:model="unit">
                                <flux:select.option value="pcs">Pieces (pcs)</flux:select.option>
                                <flux:select.option value="kg">Kilogram (kg)</flux:select.option>
                                <flux:select.option value="g">Gram (g)</flux:select.option>
                                <flux:select.option value="ltr">Liter (ltr)</flux:select.option>
                                <flux:select.option value="ml">Milliliter (ml)</flux:select.option>
                                <flux:select.option value="box">Box</flux:select.option>
                                <flux:select.option value="pack">Pack</flux:select.option>
                                <flux:select.option value="pair">Pair</flux:select.option>
                                <flux:select.option value="set">Set</flux:select.option>
                            </flux:select>
                            <flux:error name="unit" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Physical Details --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Physical Details <flux:badge color="zinc" size="sm">Optional</flux:badge></flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Weight (kg)</flux:label>
                            <flux:input wire:model="weight" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="weight" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Dimensions (L×W×H)</flux:label>
                            <flux:input wire:model="dimensions" placeholder="e.g. 10×5×3 cm" />
                            <flux:error name="dimensions" />
                        </flux:field>
                    </div>
                </flux:card>

            </div>

            {{-- Right Column (Meta) --}}
            <div class="space-y-6">

                {{-- Product Image --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Product Image</flux:heading>

                    @if($media)
                        <div class="relative">
                            <img src="{{ $media->file_url }}" class="w-full rounded-lg object-cover aspect-square" />
                            <flux:button
                                icon="x-mark"
                                variant="ghost"
                                size="sm"
                                square
                                class="absolute top-2 right-2 bg-white dark:bg-zinc-800"
                                wire:click="removeMedia"
                            />
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="$set('showMediaPicker', true)"
                            class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 p-8 text-zinc-400 hover:border-zinc-400 hover:text-zinc-500 dark:border-zinc-700"
                        >
                            <flux:icon name="photo" class="size-8" />
                            <flux:text>Click to select image</flux:text>
                        </button>
                    @endif
                </flux:card>

                {{-- Organization --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Organization</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Category</flux:label>
                            <flux:select wire:model="category_id" placeholder="Select category">
                                <flux:select.option value="">No Category</flux:select.option>
                                @foreach($categories as $category)
                                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="category_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Supplier</flux:label>
                            <flux:select wire:model="supplier_id" placeholder="Select supplier">
                                <flux:select.option value="">No Supplier</flux:select.option>
                                @foreach($suppliers as $supplier)
                                    <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="supplier_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Status</flux:label>
                            <flux:select wire:model="status">
                                <flux:select.option value="active">Active</flux:select.option>
                                <flux:select.option value="inactive">Inactive</flux:select.option>
                                <flux:select.option value="draft">Draft</flux:select.option>
                            </flux:select>
                            <flux:error name="status" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Actions --}}
                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->product?->exists ? 'Update Product' : 'Save Product' }}
                        </flux:button>
                        <flux:button variant="ghost" class="w-full" href="{{ route('products.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

    {{-- Media Picker Modal --}}
    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading>Select Image</flux:heading>
            <livewire:media.media-picker wire:select-media="selectMedia" />
        </div>
    </flux:modal>

</div>

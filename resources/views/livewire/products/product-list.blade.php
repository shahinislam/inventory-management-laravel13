<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Products</flux:heading>
            <flux:text class="mt-1">Manage your product inventory</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('products.create') }}" wire:navigate>
            Add Product
        </flux:button>
    </div>

    {{-- Search & Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="flex flex-wrap items-center gap-3">

            {{-- Search --}}
            <div class="flex-1 min-w-64">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, SKU or barcode..."
                    icon="magnifying-glass"
                    autofocus autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />
            </div>

            {{-- Toggle Filters --}}
            <flux:button
                icon="funnel"
                variant="ghost"
                wire:click="$toggle('showFilters')"
            >Filters {{ $showFilters ? '▲' : '▼' }}</flux:button>

            {{-- Export --}}
            <flux:button icon="arrow-down-tray" variant="ghost">Export</flux:button>

        </div>

        {{-- Advanced Filters --}}
        @if($showFilters)
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">

            <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach($categories as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="supplierFilter" placeholder="All Suppliers">
                <flux:select.option value="">All Suppliers</flux:select.option>
                @foreach($suppliers as $supplier)
                    <flux:select.option value="{{ $supplier->id }}">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
                <flux:select.option value="draft">Draft</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="stockFilter" placeholder="All Stock">
                <flux:select.option value="">All Stock</flux:select.option>
                <flux:select.option value="low">Low Stock</flux:select.option>
                <flux:select.option value="out">Out of Stock</flux:select.option>
            </flux:select>

        </div>
        @endif
    </flux:card>

    {{-- Products Table --}}
    <flux:card>
        <flux:table :paginate="$products">
            <flux:table.columns>
                <flux:table.column class="w-16">Image</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDir" wire:click="sort('name')">Product</flux:table.column>
                <flux:table.column>SKU</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'selling_price'" :direction="$sortDir" wire:click="sort('selling_price')">Price</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'quantity'" :direction="$sortDir" wire:click="sort('quantity')">Stock</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($products as $product)
                    <flux:table.row wire:key="{{ $product->id }}">

                        <flux:table.cell>
                            @if($product->media)
                                <img src="{{ $product->media->file_url }}" class="size-10 rounded-lg object-cover" />
                            @else
                                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="photo" class="size-5 text-zinc-400" />
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
                            <div>
                                <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                @if($product->barcode)
                                    <flux:text class="text-xs text-zinc-400">{{ $product->barcode }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge variant="outline" size="sm">{{ $product->sku }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $product->category?->name ?? '-' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div>
                                <flux:text class="font-medium">{{ money($product->selling_price) }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">Cost: {{ money($product->cost_price) }}</flux:text>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$product->isOutOfStock() ? 'red' : ($product->isLowStock() ? 'yellow' : 'green')"
                            >{{ $product->quantity }} {{ $product->unit }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="match($product->status) {
                                    'active'   => 'green',
                                    'inactive' => 'red',
                                    default    => 'zinc'
                                }"
                            >{{ ucfirst($product->status) }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('products.edit', $product)" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $product->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="cube" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No products found</flux:text>
                                <flux:button size="sm" href="{{ route('products.create') }}" wire:navigate>Add your first product</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete Product</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

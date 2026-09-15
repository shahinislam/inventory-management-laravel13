<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Categories</flux:heading>
            <flux:text class="mt-1">Manage product categories</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('categories.create') }}" wire:navigate>
            Add Category
        </flux:button>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Search & Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search categories..."
                icon="magnifying-glass"
                class="flex-1" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />
            <flux:select wire:model.live="statusFilter" class="w-36">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$categories">
            <flux:table.columns>
                <flux:table.column class="w-12">Icon</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Parent</flux:table.column>
                <flux:table.column>Children</flux:table.column>
                <flux:table.column>Products</flux:table.column>
                <flux:table.column>Order</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($categories as $category)
                    <flux:table.row wire:key="{{ $category->id }}">

                        {{-- Icon/Image --}}
                        <flux:table.cell>
                            @if($category->media)
                                <img src="{{ $category->media->file_url }}" class="size-8 rounded-lg object-cover" />
                            @else
                                <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="tag" class="size-4 text-zinc-400" />
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Name --}}
                        <flux:table.cell>
                            <div>
                                <flux:text class="font-medium">{{ $category->name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $category->slug }}</flux:text>
                            </div>
                        </flux:table.cell>

                        {{-- Parent --}}
                        <flux:table.cell>
                            @if($category->parent)
                                <flux:badge size="sm" color="blue">{{ $category->parent->name }}</flux:badge>
                            @else
                                <flux:text class="text-sm text-zinc-400">Root</flux:text>
                            @endif
                        </flux:table.cell>

                        {{-- Children --}}
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $category->children->count() }}</flux:text>
                        </flux:table.cell>

                        {{-- Products --}}
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $category->products_count }}</flux:badge>
                        </flux:table.cell>

                        {{-- Order --}}
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $category->order }}</flux:text>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$category->is_active ? 'green' : 'red'"
                                class="cursor-pointer"
                                wire:click="toggleStatus({{ $category->id }})"
                            >{{ $category->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                        </flux:table.cell>

                        {{-- Actions --}}
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('categories.edit', $category)" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $category->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="tag" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No categories found</flux:text>
                                <flux:button size="sm" href="{{ route('categories.create') }}" wire:navigate>
                                    Add your first category
                                </flux:button>
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
            <flux:heading>Delete Category</flux:heading>
            <flux:text class="mt-2">Are you sure? Child categories will be moved to parent level.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

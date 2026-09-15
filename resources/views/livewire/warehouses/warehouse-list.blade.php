<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Warehouses</flux:heading>
            <flux:text class="mt-1">Manage your storage locations</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('warehouses.create') }}" wire:navigate>
            Add Warehouse
        </flux:button>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    {{-- Search & Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name or code..."
                icon="magnifying-glass"
                class="flex-1" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="warehouse-list-search-efe386-nofill" />
            <flux:select wire:model.live="statusFilter" class="w-36">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$warehouses">
            <flux:table.columns>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Location</flux:table.column>
                <flux:table.column>Manager</flux:table.column>
                <flux:table.column>Capacity</flux:table.column>
                <flux:table.column>Movements</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($warehouses as $warehouse)
                    <flux:table.row wire:key="{{ $warehouse->id }}">

                        {{-- Name --}}
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="building-storefront" class="size-4 text-zinc-400" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">{{ $warehouse->name }}</flux:text>
                                    @if($warehouse->is_default)
                                        <flux:badge size="sm" color="blue">Default</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        {{-- Code --}}
                        <flux:table.cell>
                            <flux:badge variant="outline" size="sm">{{ $warehouse->code }}</flux:badge>
                        </flux:table.cell>

                        {{-- Location --}}
                        <flux:table.cell>
                            <flux:text class="text-sm">
                                {{ collect([$warehouse->city, $warehouse->country])->filter()->implode(', ') ?: '-' }}
                            </flux:text>
                        </flux:table.cell>

                        {{-- Manager --}}
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $warehouse->manager?->name ?? '-' }}</flux:text>
                        </flux:table.cell>

                        {{-- Capacity --}}
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $warehouse->capacity ? number_format($warehouse->capacity) : '-' }}</flux:text>
                        </flux:table.cell>

                        {{-- Movements --}}
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $warehouse->stock_movements_count }}</flux:badge>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$warehouse->is_active ? 'green' : 'red'"
                                class="cursor-pointer"
                                wire:click="toggleStatus({{ $warehouse->id }})"
                            >{{ $warehouse->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                        </flux:table.cell>

                        {{-- Actions --}}
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('warehouses.edit', $warehouse)" wire:navigate>Edit</flux:menu.item>
                                    @if(!$warehouse->is_default)
                                        <flux:menu.item icon="star" wire:click="setDefault({{ $warehouse->id }})">Set as Default</flux:menu.item>
                                    @endif
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $warehouse->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="building-storefront" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No warehouses found</flux:text>
                                <flux:button size="sm" href="{{ route('warehouses.create') }}" wire:navigate>
                                    Add your first warehouse
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
            <flux:heading>Delete Warehouse</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

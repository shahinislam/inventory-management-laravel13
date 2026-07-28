<div class="p-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Customers</flux:heading>
            <flux:text class="mt-1">Manage your customers</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('customers.create') }}" wire:navigate>
            Add Customer
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
                placeholder="Search by name, email or phone..."
                icon="magnifying-glass"
                class="flex-1"
            />
            <flux:select wire:model.live="statusFilter" style="width:140px">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$customers">
            <flux:table.columns>
                <flux:table.column class="w-12">Photo</flux:table.column>
                <flux:table.column>Customer</flux:table.column>
                <flux:table.column>Contact</flux:table.column>
                <flux:table.column>Total Orders</flux:table.column>
                <flux:table.column>Total Spent</flux:table.column>
                <flux:table.column>Balance</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($customers as $customer)
                    <flux:table.row wire:key="{{ $customer->id }}">

                        {{-- Photo --}}
                        <flux:table.cell>
                            @if($customer->media)
                                <img src="{{ $customer->media->file_url }}" class="size-8 rounded-full object-cover" />
                            @else
                                <div class="flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="user" class="size-4 text-zinc-400" />
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Name --}}
                        <flux:table.cell>
                            <div>
                                <flux:text class="font-medium">{{ $customer->name }}</flux:text>
                                @if($customer->city)
                                    <flux:text class="text-xs text-zinc-400">{{ $customer->city }}</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Contact --}}
                        <flux:table.cell>
                            <div>
                                @if($customer->phone)
                                    <flux:text class="text-sm">{{ $customer->phone }}</flux:text>
                                @endif
                                @if($customer->email)
                                    <flux:text class="text-xs text-zinc-400">{{ $customer->email }}</flux:text>
                                @endif
                                @if(!$customer->phone && !$customer->email)
                                    <flux:text class="text-sm text-zinc-400">-</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>

                        {{-- Total Orders --}}
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $customer->total_orders }}</flux:badge>
                        </flux:table.cell>

                        {{-- Total Spent --}}
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ money($customer->total_purchases) }}</flux:text>
                        </flux:table.cell>

                        {{-- Balance --}}
                        <flux:table.cell>
                            <flux:text class="text-sm {{ $customer->current_balance > 0 ? 'text-red-500' : '' }}">
                                {{ money($customer->current_balance) }}
                            </flux:text>
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$customer->is_active ? 'green' : 'red'"
                                class="cursor-pointer"
                                wire:click="toggleStatus({{ $customer->id }})"
                            >{{ $customer->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                        </flux:table.cell>

                        {{-- Actions --}}
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('customers.edit', $customer)" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $customer->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>

                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="users" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No customers found</flux:text>
                                <flux:button size="sm" href="{{ route('customers.create') }}" wire:navigate>
                                    Add your first customer
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
            <flux:heading>Delete Customer</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

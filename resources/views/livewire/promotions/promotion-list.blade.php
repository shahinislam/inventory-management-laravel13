<div class="p-4">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Promotions</flux:heading>
            <flux:text class="mt-1">Manage discounts & special offers</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('promotions.create') }}" wire:navigate>New Promotion</flux:button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <flux:card class="mb-6 p-4">
        <div class="flex items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or code..." icon="magnifying-glass" class="flex-1" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="promotion-list-search-060458-nofill" />
            <flux:select wire:model.live="statusFilter" class="w-40">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
                <flux:select.option value="expired">Expired</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <flux:card>
        <flux:table :paginate="$promotions">
            <flux:table.columns>
                <flux:table.column>Promotion</flux:table.column>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Value</flux:table.column>
                <flux:table.column>Applies To</flux:table.column>
                <flux:table.column>Validity</flux:table.column>
                <flux:table.column>Usage</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($promotions as $promo)
                    <flux:table.row wire:key="{{ $promo->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $promo->name }}</flux:text>
                            @if($promo->description)
                                <flux:text class="text-xs text-zinc-400">{{ Str::limit($promo->description, 40) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($promo->code)
                                <flux:badge variant="outline" size="sm">{{ $promo->code }}</flux:badge>
                            @else
                                <flux:text class="text-sm text-zinc-400">Auto-apply</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="blue">{{ ucfirst(str_replace('_', ' ', $promo->type)) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="font-medium">
                                {{ $promo->type === 'percentage' ? $promo->value.'%' : money($promo->value) }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">
                                {{ $promo->product?->name ?? $promo->category?->name ?? 'All Products' }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">
                                @if($promo->starts_at || $promo->ends_at)
                                    {{ $promo->starts_at?->format('d M') ?? 'Now' }} - {{ $promo->ends_at?->format('d M Y') ?? 'No end' }}
                                @else
                                    Always active
                                @endif
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $promo->used_count }}{{ $promo->usage_limit ? '/'.$promo->usage_limit : '' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge
                                size="sm"
                                :color="$promo->isActive() ? 'green' : 'red'"
                                class="cursor-pointer"
                                wire:click="toggleStatus({{ $promo->id }})"
                            >{{ $promo->isActive() ? 'Active' : ($promo->ends_at && $promo->ends_at < now() ? 'Expired' : 'Inactive') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('promotions.edit', $promo)" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $promo->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="gift" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No promotions found</flux:text>
                                <flux:button size="sm" href="{{ route('promotions.create') }}" wire:navigate>Create your first promotion</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete Promotion</flux:heading>
            <flux:text class="mt-2">Are you sure? This action cannot be undone.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

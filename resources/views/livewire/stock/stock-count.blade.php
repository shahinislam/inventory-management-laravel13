<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('stock.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">Stock Count</flux:heading>
            <flux:text class="mt-1">Count what is on the shelf, then post it to correct the system.</flux:text>
        </div>
    </div>

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

    @if(! $started)
        {{-- Step 1: what to count --}}
        <flux:card class="max-w-2xl p-6">
            <flux:heading class="mb-1">1. What are you counting?</flux:heading>
            <flux:text class="mb-4 text-sm">Pick a warehouse, and a category if you want to count one shelf at a time.</flux:text>

            <form wire:submit="start" class="space-y-4">
                <flux:field>
                    <flux:label>Warehouse</flux:label>
                    <flux:select wire:model="warehouse_id">
                        @foreach($warehouses as $warehouse)
                            <flux:select.option value="{{ $warehouse->id }}">
                                {{ $warehouse->name }} {{ $warehouse->is_default ? '(Default)' : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="warehouse_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="category_id">
                        <flux:select.option value="">All categories</flux:select.option>
                        @foreach($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category_id" />
                </flux:field>

                <flux:button type="submit" variant="primary" icon="clipboard-document-check">Start count</flux:button>
            </form>
        </flux:card>
    @else
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
            <div class="text-sm">
                <strong>Finish and post this count in one go — it isn't saved until you post.</strong>
                Counting {{ $warehouseName }}{{ $categoryName ? ' · '.$categoryName : '' }}.
                Type what you find, or scan items to add one each. Leave a row blank to keep its stock as it is.
            </div>
        </div>

        {{-- Summary --}}
        <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Products" :value="$summary['total']" icon="cube" />
            <x-stat-tile label="Counted" :value="$summary['counted']" tone="blue" icon="check" />
            <x-stat-tile label="With a difference" :value="$summary['different']" tone="yellow" icon="exclamation-triangle" />
            <x-stat-tile label="Gain / loss at cost"
                :value="($summary['value'] < 0 ? '-' : '').money(abs($summary['value']))"
                :tone="$summary['value'] < 0 ? 'red' : ($summary['value'] > 0 ? 'green' : 'default')"
                icon="banknotes" />
        </div>

        <flux:card class="mb-4 p-4">
            <flux:input wire:model.live.debounce.300ms="filter" placeholder="Find a product in this count..." icon="magnifying-glass" autocomplete="off" />
        </flux:card>

        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Product</flux:table.column>
                    <flux:table.column class="text-right">System</flux:table.column>
                    <flux:table.column>Counted</flux:table.column>
                    <flux:table.column class="text-right">Difference</flux:table.column>
                    <flux:table.column class="text-right">Value at cost</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($visible as $id => $row)
                        <flux:table.row wire:key="count-{{ $id }}">
                            <flux:table.cell>
                                <flux:text class="font-medium">{{ $row['name'] }}</flux:text>
                                <flux:text class="font-mono text-xs text-zinc-400">{{ $row['sku'] }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell class="text-right tabular-nums">{{ format_qty($row['system'], $row['unit']) }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:input class="w-28" size="sm" type="number" min="0"
                                        step="{{ $row['loose'] ? '0.001' : '1' }}"
                                        wire:model.live.debounce.400ms="rows.{{ $id }}.counted"
                                        placeholder="—" />
                                    <flux:text class="text-xs text-zinc-400">{{ $row['unit'] }}</flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-right tabular-nums">
                                @if($row['difference'] === null)
                                    <flux:text class="text-zinc-400">Not counted</flux:text>
                                @elseif(abs($row['difference']) < 0.0005)
                                    <flux:badge size="sm" color="green">Matches</flux:badge>
                                @else
                                    <span class="{{ $row['difference'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }} font-medium">
                                        {{ $row['difference'] > 0 ? '+' : '' }}{{ format_qty($row['difference'], $row['unit']) }}
                                    </span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-right tabular-nums">
                                @if($row['value'] !== null && abs($row['value']) >= 0.005)
                                    <span class="{{ $row['value'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                        {{ $row['value'] < 0 ? '-' : '+' }}{{ money(abs($row['value'])) }}
                                    </span>
                                @else
                                    <flux:text class="text-zinc-400">—</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="magnifying-glass" class="size-10 text-zinc-300" />
                                    <flux:text class="text-zinc-400">
                                        {{ $filter !== '' ? 'No product in this count matches your search.' : 'There are no active products to count here.' }}
                                    </flux:text>
                                    @if($filter === '')
                                        <flux:button size="sm" variant="outline" wire:click="cancel">Choose something else</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if($canPost)
                <flux:button variant="primary" icon="check" wire:click="post"
                    wire:confirm="Post this count? Stock will be corrected for {{ $summary['different'] }} product(s)."
                    :disabled="$summary['counted'] === 0">
                    Post count
                </flux:button>
            @else
                <flux:text class="text-sm">Ask a manager to post this count — only managers and admins can change stock from a count.</flux:text>
            @endif
            <flux:button variant="ghost" wire:click="cancel" wire:confirm="Throw away this count? Nothing has been saved.">Discard</flux:button>
        </div>
    @endif

</div>

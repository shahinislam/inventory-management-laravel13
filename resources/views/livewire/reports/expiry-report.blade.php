<div class="p-4" data-print-report="Expiry Report">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <flux:heading size="xl">Expiry Report</flux:heading>
            <flux:text class="mt-1">
                Batches come from receiving purchases with an expiry date. Stock received without one isn't listed here.
            </flux:text>
        </div>
        <flux:button icon="printer" variant="outline" onclick="window.print()">Print</flux:button>
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

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-tile label="Expired" :value="$expiredCount.' '.Str::plural('batch', $expiredCount)" tone="red" icon="x-circle"
            :hint="money($expiredValue).' at cost — sell none of it'" class="border-l-4 border-l-red-500" />
        <x-stat-tile label="Expiring within {{ $window }} days" :value="$soonCount.' '.Str::plural('batch', $soonCount)" tone="yellow" icon="clock"
            :hint="money($soonValue).' at cost — sell these first'" class="border-l-4 border-l-yellow-500" />
    </div>

    {{-- Tabs & filters --}}
    <flux:card class="mb-6 p-4 print:hidden">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" :variant="$tab === 'expired' ? 'primary' : 'outline'" wire:click="setTab('expired')">Expired</flux:button>
                <flux:button size="sm" :variant="$tab === 'soon' ? 'primary' : 'outline'" wire:click="setTab('soon')">Expiring soon</flux:button>
                <flux:button size="sm" :variant="$tab === 'all' ? 'primary' : 'outline'" wire:click="setTab('all')">All batches</flux:button>
            </div>
            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr_8rem]">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search product..." icon="magnifying-glass" autocomplete="off" />
                <flux:select wire:model.live="warehouseFilter">
                    <flux:select.option value="">All warehouses</flux:select.option>
                    @foreach($warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live.debounce.400ms="days" type="number" min="0" max="3650" suffix="days" title="How many days ahead counts as expiring soon" />
            </div>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$batches">
            <flux:table.columns>
                <flux:table.column>Product</flux:table.column>
                <flux:table.column>Warehouse</flux:table.column>
                <flux:table.column>Batch</flux:table.column>
                <flux:table.column>Expiry date</flux:table.column>
                <flux:table.column>Days left</flux:table.column>
                <flux:table.column class="text-right">Quantity</flux:table.column>
                <flux:table.column class="text-right">Value at cost</flux:table.column>
                @if($canWriteOff)
                    <flux:table.column class="text-right print:hidden">Action</flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse($batches as $batch)
                    @php($left = $batch->daysLeft())
                    <flux:table.row wire:key="batch-{{ $batch->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $batch->product?->name ?? 'Deleted product' }}</flux:text>
                            <flux:text class="font-mono text-xs text-zinc-400">{{ $batch->product?->sku }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $batch->warehouse?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $batch->batch_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $batch->expiry_date?->format('d M Y') ?? 'No expiry' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($left === null)
                                <flux:text class="text-zinc-400">—</flux:text>
                            @elseif($left < 0)
                                <flux:badge size="sm" color="red">Expired {{ abs($left) }} {{ Str::plural('day', abs($left)) }} ago</flux:badge>
                            @elseif($left === 0)
                                <flux:badge size="sm" color="red">Expires today</flux:badge>
                            @elseif($left <= $window)
                                <flux:badge size="sm" color="amber">{{ $left }} {{ Str::plural('day', $left) }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ $left }} days</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ format_qty($batch->quantity, $batch->product?->unit) }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ money($batch->quantity * (float) $batch->unit_cost) }}</flux:table.cell>
                        @if($canWriteOff)
                            <flux:table.cell class="text-right print:hidden">
                                @if($batch->isExpired())
                                    <flux:button size="sm" variant="danger" icon="trash"
                                        wire:click="writeOff({{ $batch->id }})"
                                        wire:confirm="Write off {{ format_qty($batch->quantity, $batch->product?->unit) }} of {{ $batch->product?->name }}? It will be removed from stock as expired.">
                                        Write off
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ $canWriteOff ? 8 : 7 }}" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="check-circle" class="size-10 text-green-400" />
                                <flux:text class="text-zinc-400">
                                    @if($tab === 'expired')
                                        No expired stock — nice.
                                    @elseif($tab === 'soon')
                                        Nothing expires in the next {{ $window }} days.
                                    @else
                                        No batches yet. Enter an expiry date when you receive a purchase to track it here.
                                    @endif
                                </flux:text>
                                @if($tab === 'all' && Route::has('purchases.index'))
                                    <flux:button size="sm" variant="outline" icon="truck" href="{{ route('purchases.index') }}" wire:navigate>Go to purchases</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

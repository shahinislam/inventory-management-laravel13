<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">Print price labels</flux:heading>
            <flux:text class="mt-1">Search or scan products, set how many labels you need, then print.</flux:text>
        </div>
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('products.index') }}" wire:navigate>
            Back to products
        </flux:button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Product picker --}}
        <flux:card class="p-4 lg:col-span-2">
            <div class="relative">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, SKU or barcode, or scan a barcode"
                    icon="magnifying-glass"
                    autofocus autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false"
                    name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />

                @if (strlen(trim($search)) > 0)
                    <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                        @forelse ($results as $result)
                            <button type="button" wire:click="addProduct({{ $result->id }})" wire:key="result-{{ $result->id }}"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <span>
                                    <span class="font-medium">{{ $result->name }}</span>
                                    <span class="block text-xs text-zinc-500">
                                        SKU {{ $result->sku }}@if ($result->barcode) · {{ $result->barcode }}@endif
                                    </span>
                                </span>
                                <span class="whitespace-nowrap font-medium">
                                    {{ money($result->selling_price) }}@if ($result->isLoose())<span class="text-xs text-zinc-500"> / {{ $result->unit }}</span>@endif
                                </span>
                            </button>
                        @empty
                            <div class="px-3 py-3 text-sm text-zinc-500">No products match "{{ $search }}".</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="mt-4">
                @if (count($items) === 0)
                    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-600">
                        <flux:icon.tag class="mb-3 size-10 text-zinc-400" />
                        <flux:heading>No products on the list yet</flux:heading>
                        <flux:text class="mt-1 max-w-md">
                            Search above or scan a product's barcode to add it. Scanning the same product again adds one more copy.
                            For loose items (kg, g, ltr, ml) you can enter a weight to print the packed price.
                        </flux:text>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Product</flux:table.column>
                            <flux:table.column>Price</flux:table.column>
                            <flux:table.column class="w-28">Copies</flux:table.column>
                            <flux:table.column class="w-40">Weight</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($items as $index => $item)
                                <flux:table.row wire:key="item-{{ $item['id'] }}">
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $item['name'] }}</div>
                                        <div class="text-xs text-zinc-500">
                                            {{ $item['barcode'] ?: 'SKU '.$item['sku'] }}
                                        </div>
                                        @unless ($item['barcode'])
                                            <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">No barcode — SKU will be printed</div>
                                        @endunless
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ money($item['price']) }}@if ($item['loose'])<span class="text-xs text-zinc-500"> / {{ $item['unit'] }}</span>@endif
                                        @if ($item['loose'] && (float) $item['weight'] > 0)
                                            <div class="text-xs text-zinc-500">
                                                {{ format_qty($item['weight'], $item['unit']) }} = {{ money((float) $item['weight'] * $item['price']) }}
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:input type="number" min="1" max="500" size="sm"
                                            wire:model.live.debounce.400ms="items.{{ $index }}.copies" />
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($item['loose'])
                                            <flux:input type="number" min="0" step="0.001" size="sm" placeholder="Optional ({{ $item['unit'] }})"
                                                wire:model.live.debounce.400ms="items.{{ $index }}.weight" />
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeItem({{ $index }})" aria-label="Remove" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </flux:card>

        {{-- Print options --}}
        <flux:card class="h-fit space-y-4 p-4">
            <flux:heading>Print options</flux:heading>

            <flux:select wire:model.live="size" label="Label size">
                @foreach ($sizes as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:text>
                {{ $totalLabels }} {{ Str::plural('label', $totalLabels) }} from {{ count($items) }} {{ Str::plural('product', count($items)) }}.
                @if ($size === 'a4' && $totalLabels > 0)
                    {{ (int) ceil($totalLabels / 24) }} A4 {{ Str::plural('sheet', (int) ceil($totalLabels / 24)) }}.
                @endif
            </flux:text>

            @if ($printUrl)
                <flux:button variant="primary" icon="printer" class="w-full" href="{{ $printUrl }}" target="_blank">
                    Print labels
                </flux:button>
                <flux:button variant="ghost" class="w-full" wire:click="clearItems">Clear list</flux:button>
            @else
                <flux:button variant="primary" icon="printer" class="w-full" disabled>Print labels</flux:button>
                <flux:text class="text-xs">Add at least one product to print.</flux:text>
            @endif
        </flux:card>
    </div>
</div>

<div class="p-4">

    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('membership-rewards.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->reward?->exists ? 'Edit Reward' : 'New Reward' }}</flux:heading>
            <flux:text class="mt-1">Decide when a member earns it and what they get</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">

            <div class="space-y-6">

                {{-- 1. Name --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">1. Name</flux:heading>
                    <flux:field>
                        <flux:label>Reward name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                        <flux:input wire:model.live.debounce.400ms="name" placeholder="e.g. Big basket discount" />
                        <flux:description>Shown to the cashier at the POS.</flux:description>
                        <flux:error name="name" />
                    </flux:field>
                </flux:card>

                {{-- 2. When --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-1">2. When does the member earn it?</flux:heading>
                    <flux:text class="mb-4 text-sm">Pick how the spending is counted.</flux:text>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['single_invoice', 'On a single bill', 'The amount of this purchase falls in a range. Can be earned on every qualifying bill.', 'receipt-percent'],
                            ['cumulative', 'Total spend milestone', 'Everything the member has bought adds up past a target. Given once per member.', 'trophy'],
                        ] as [$value, $title, $help, $icon])
                            <label wire:key="basis-{{ $value }}" @class([
                                'flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors',
                                'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $basis === $value,
                                'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50' => $basis !== $value,
                            ])>
                                <input type="radio" wire:model.live="basis" value="{{ $value }}" class="sr-only" />
                                <flux:icon :name="$icon" class="size-6 shrink-0 text-zinc-500" />
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $title }}</div>
                                    <div class="mt-0.5 text-xs text-zinc-500">{{ $help }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>
                                {{ $basis === 'cumulative' ? 'Total spend reaches' : 'Bill from' }}
                                <flux:badge color="red" size="sm">Required</flux:badge>
                            </flux:label>
                            <flux:input wire:model.live.debounce.400ms="min_amount" type="number" step="0.01" min="0" prefix="৳"
                                placeholder="{{ $basis === 'cumulative' ? 'e.g. 50000' : 'e.g. 5000' }}" />
                            <flux:error name="min_amount" />
                        </flux:field>

                        @if ($basis === 'single_invoice')
                            <flux:field>
                                <flux:label>Bill up to</flux:label>
                                <flux:input wire:model.live.debounce.400ms="max_amount" type="number" step="0.01" min="0" prefix="৳"
                                    placeholder="No upper limit" />
                                <flux:description>Leave empty for “or more”.</flux:description>
                                <flux:error name="max_amount" />
                            </flux:field>
                        @endif
                    </div>
                </flux:card>

                {{-- 3. What --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">3. What does the member get?</flux:heading>

                    <flux:radio.group wire:model.live="reward_type" variant="segmented" class="mb-4">
                        <flux:radio value="percent" label="% Discount" icon="receipt-percent" />
                        <flux:radio value="fixed" label="Fixed Discount" icon="banknotes" />
                        <flux:radio value="gift" label="Free Gift" icon="gift" />
                    </flux:radio.group>

                    @if ($reward_type === 'gift')
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_8rem]">
                            <flux:field>
                                <flux:label>Gift product <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:select wire:model.live="gift_product_id">
                                    <flux:select.option value="">Choose a product...</flux:select.option>
                                    @foreach ($products as $p)
                                        <flux:select.option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:description>Added to the bill free of charge and taken out of stock.</flux:description>
                                <flux:error name="gift_product_id" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Quantity</flux:label>
                                <flux:input wire:model.live.debounce.400ms="gift_quantity" type="number" min="1" />
                                <flux:error name="gift_quantity" />
                            </flux:field>
                        </div>
                    @else
                        <flux:field class="max-w-xs">
                            <flux:label>{{ $reward_type === 'percent' ? 'Discount percentage' : 'Discount amount' }} <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model.live.debounce.400ms="value" type="number" step="0.01" min="0"
                                :suffix="$reward_type === 'percent' ? '%' : null" :prefix="$reward_type === 'fixed' ? '৳' : null"
                                placeholder="{{ $reward_type === 'percent' ? 'e.g. 5' : 'e.g. 200' }}" />
                            <flux:description>Taken off the bill. The cashier can still adjust it at checkout.</flux:description>
                            <flux:error name="value" />
                        </flux:field>
                    @endif
                </flux:card>

                <flux:card class="p-6">
                    <flux:field>
                        <flux:label>Notes (optional)</flux:label>
                        <flux:textarea wire:model="notes" rows="2" placeholder="Anything staff should know..." />
                    </flux:field>
                </flux:card>
            </div>

            <div class="space-y-6">
                {{-- Live preview in plain words --}}
                <flux:card class="p-6 lg:sticky lg:top-4">
                    <flux:heading class="mb-3">Preview</flux:heading>
                    <div class="rounded-xl border border-pink-200 bg-pink-50/60 p-4 dark:border-pink-900/50 dark:bg-pink-950/20">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-pink-700 dark:text-pink-300">
                            <flux:icon name="gift" class="size-4" /> {{ $name ?: 'Untitled reward' }}
                        </div>
                        <div class="mt-2 text-sm text-zinc-800 dark:text-zinc-200">
                            <span class="font-medium">When:</span> {{ $preview->range_label }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">
                            <span class="font-medium">Member gets:</span> {{ $preview->reward_label }}
                        </div>
                        <div class="mt-2 text-xs text-zinc-500">
                            {{ $basis === 'cumulative' ? 'Given once per member, on the purchase that crosses the target.' : 'Offered on every bill in this range.' }}
                            The cashier chooses to give it or skip it.
                        </div>
                    </div>

                    @if ($this->overlaps->isNotEmpty())
                        <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                            <div class="font-semibold">Overlaps with:</div>
                            <ul class="mt-1 list-disc ps-4">
                                @foreach ($this->overlaps as $o)
                                    <li>{{ $o->name }} — {{ $o->range_label }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-1">That's allowed. The cashier will see both and can pick.</div>
                        </div>
                    @endif

                    <div class="mt-5 flex items-center justify-between">
                        <flux:label>Active</flux:label>
                        <flux:switch wire:model="is_active" />
                    </div>

                    <div class="mt-5 space-y-3">
                        <flux:button type="submit" variant="primary" class="w-full" icon="check">
                            {{ $this->reward?->exists ? 'Update Reward' : 'Save Reward' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('membership-rewards.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </form>

</div>

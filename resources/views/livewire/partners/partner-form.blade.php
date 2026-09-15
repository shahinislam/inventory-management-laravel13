<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-3">
        <flux:button icon="arrow-left" variant="ghost" :href="route('partners.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $partner?->exists ? 'Edit Partner' : 'Add Partner' }}</flux:heading>
            <flux:text class="mt-1">Ownership share determines how profit is split</flux:text>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Details --}}
        <flux:card class="p-6 lg:col-span-2">
            <flux:heading class="mb-4">Details</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="Partner name" />
                    <flux:error name="name" />
                </flux:field>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Email</flux:label>
                        <flux:input wire:model="email" type="email" placeholder="optional" />
                        <flux:error name="email" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Phone</flux:label>
                        <flux:input wire:model="phone" placeholder="optional" />
                        <flux:error name="phone" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="3" placeholder="optional" />
                    <flux:error name="notes" />
                </flux:field>
            </div>
        </flux:card>

        {{-- Share --}}
        <div class="space-y-6">
            <flux:card class="p-6">
                <flux:heading class="mb-4">Ownership</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Share percentage</flux:label>
                        <flux:input wire:model.live="share_percentage" type="number" step="0.01" min="0"
                            max="100" suffix="%" class="tabular-nums" />
                        <flux:error name="share_percentage" />
                    </flux:field>

                    <flux:checkbox wire:model.live="is_active" label="Active partner" />

                    {{-- Live running total, so it is obvious before saving whether
                         the shares will add up. --}}
                    <div class="space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Other active partners</span>
                            <span class="tabular-nums text-zinc-900 dark:text-white">
                                {{ rtrim(rtrim(number_format($this->otherShare, 2), '0'), '.') }}%
                            </span>
                        </div>
                        <div class="flex justify-between font-medium">
                            <span class="text-zinc-500">Resulting total</span>
                            @php $resulting = round($this->resultingShare, 2); @endphp
                            <span @class([
                                'tabular-nums',
                                'text-green-600 dark:text-green-400' => $resulting === 100.0,
                                'text-amber-600 dark:text-amber-400' => $resulting < 100.0,
                                'text-red-600 dark:text-red-400' => $resulting > 100.0,
                            ])>
                                {{ rtrim(rtrim(number_format($resulting, 2), '0'), '.') }}%
                            </span>
                        </div>

                        @if ($resulting > 100.0)
                            <flux:text size="sm" class="text-red-600 dark:text-red-400">
                                Shares cannot exceed 100%.
                            </flux:text>
                        @elseif ($resulting < 100.0)
                            <flux:text size="sm" class="text-amber-600 dark:text-amber-400">
                                {{ rtrim(rtrim(number_format(100 - $resulting, 2), '0'), '.') }}% still unallocated.
                            </flux:text>
                        @endif
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="space-y-3">
                    <flux:button type="submit" variant="primary" class="w-full" icon="check">
                        {{ $partner?->exists ? 'Save Changes' : 'Add Partner' }}
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" :href="route('partners.index')" wire:navigate>
                        Cancel
                    </flux:button>
                </div>
            </flux:card>
        </div>
    </form>
</div>

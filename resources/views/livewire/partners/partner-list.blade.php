<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Partners</flux:heading>
            <flux:text class="mt-1">Ownership shares and capital accounts</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button icon="banknotes" variant="outline" :href="route('partners.transactions')" wire:navigate>
                Transactions
            </flux:button>
            <flux:button icon="plus" variant="primary" :href="route('partners.create')" wire:navigate>
                Add Partner
            </flux:button>
        </div>
    </div>

    {{-- Shares must account for the whole business, or profit ends up
         unallocated or over-allocated when the report splits it. --}}
    @if (round($totalShare, 2) !== 100.0)
        <flux:card class="mb-6 border-amber-300 p-4 dark:border-amber-800">
            <div class="flex items-start gap-3">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <flux:heading size="sm" class="text-amber-700 dark:text-amber-400">
                        Active shares total {{ rtrim(rtrim(number_format($totalShare, 2), '0'), '.') }}%, not 100%
                    </flux:heading>
                    <flux:text size="sm" class="mt-1">
                        The partnership report splits profit by these percentages, so the figures will not add up
                        until they total 100%.
                    </flux:text>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Search --}}
    <flux:card class="mb-6 p-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search partners…" icon="magnifying-glass"
            class="max-w-sm" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="partner-list-search-96a9bb-nofill" />
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Partner</flux:table.column>
                <flux:table.column>Contact</flux:table.column>
                <flux:table.column>Share</flux:table.column>
                <flux:table.column>Invested</flux:table.column>
                <flux:table.column>Withdrawn</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($partners as $partner)
                    <flux:table.row wire:key="partner-{{ $partner->id }}">
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $partner->name }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm text-zinc-500">
                                {{ $partner->email ?: '—' }}
                                @if ($partner->phone)
                                    <div>{{ $partner->phone }}</div>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums font-medium text-zinc-900 dark:text-white">
                                {{ rtrim(rtrim(number_format($partner->share_percentage, 2), '0'), '.') }}%
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums text-green-600 dark:text-green-400">
                                {{ money($partner->invested ?? 0) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ money($partner->withdrawn ?? 0) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$partner->is_active ? 'green' : 'zinc'">
                                {{ $partner->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" icon="pencil"
                                    :href="route('partners.edit', $partner)" wire:navigate />
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="$set('deleteId', {{ $partner->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center">
                            <flux:text>No partners yet.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($partners->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $partners->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Delete confirmation --}}
    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading size="lg">Remove partner?</flux:heading>
            <flux:text class="mt-2">
                Their investment and withdrawal history is kept, but they will no longer appear in the
                partnership report.
            </flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

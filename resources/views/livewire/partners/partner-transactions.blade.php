<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button icon="arrow-left" variant="ghost" :href="route('partners.index')" wire:navigate />
            <div>
                <flux:heading size="xl">Partner Transactions</flux:heading>
                <flux:text class="mt-1">Capital contributions and drawings</flux:text>
            </div>
        </div>
        <div class="flex gap-3">
            <flux:button icon="arrow-down-tray" variant="primary" wire:click="openModal('investment')">
                Record Investment
            </flux:button>
            <flux:button icon="arrow-up-tray" variant="outline" wire:click="openModal('withdrawal')">
                Record Withdrawal
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="typeFilter" class="w-44">
                <flux:select.option value="">All types</flux:select.option>
                <flux:select.option value="investment">Investments</flux:select.option>
                <flux:select.option value="withdrawal">Withdrawals</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="partnerFilter" class="w-52">
                <flux:select.option value="">All partners</flux:select.option>
                @foreach ($partners as $partner)
                    <flux:select.option value="{{ $partner->id }}">{{ $partner->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Partner</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Amount</flux:table.column>
                <flux:table.column>Reference</flux:table.column>
                <flux:table.column>Recorded by</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($transactions as $txn)
                    <flux:table.row wire:key="txn-{{ $txn->id }}">
                        <flux:table.cell>{{ $txn->transaction_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="font-medium text-zinc-900 dark:text-white">
                                {{ $txn->partner?->name ?? '—' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$txn->isInvestment() ? 'green' : 'amber'">
                                {{ $txn->isInvestment() ? 'Investment' : 'Withdrawal' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span @class([
                                'tabular-nums font-medium',
                                'text-green-600 dark:text-green-400' => $txn->isInvestment(),
                                'text-amber-600 dark:text-amber-400' => ! $txn->isInvestment(),
                            ])>
                                {{ $txn->isInvestment() ? '+' : '−' }}{{ money($txn->amount) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-zinc-500">{{ $txn->reference ?: '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="text-sm text-zinc-500">{{ $txn->createdBy?->name ?? '—' }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end">
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    wire:click="$set('deleteId', {{ $txn->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center">
                            <flux:text>No transactions recorded yet.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($transactions->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $transactions->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Record entry --}}
    <flux:modal wire:model="showModal" class="max-w-md">
        <form wire:submit="save" class="p-6">
            <flux:heading size="lg">
                Record {{ $type === 'investment' ? 'Investment' : 'Withdrawal' }}
            </flux:heading>

            <div class="mt-5 space-y-4">
                <flux:field>
                    <flux:label>Partner</flux:label>
                    <flux:select wire:model="partner_id">
                        <flux:select.option value="">Select a partner…</flux:select.option>
                        @foreach ($partners as $partner)
                            <flux:select.option value="{{ $partner->id }}">{{ $partner->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="partner_id" />
                </flux:field>

                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label>Amount</flux:label>
                        <flux:input wire:model="amount" type="number" step="0.01" min="0.01" prefix="৳"
                            class="tabular-nums" />
                        <flux:error name="amount" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Date</flux:label>
                        <flux:input wire:model="transaction_date" type="date" />
                        <flux:error name="transaction_date" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Reference</flux:label>
                    <flux:input wire:model="reference" placeholder="Cheque no, bank ref — optional" />
                    <flux:error name="reference" />
                </flux:field>

                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="notes" rows="2" placeholder="optional" />
                    <flux:error name="notes" />
                </flux:field>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showModal', false)" type="button">Cancel</flux:button>
                <flux:button variant="primary" type="submit">Record</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation --}}
    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading size="lg">Remove entry?</flux:heading>
            <flux:text class="mt-2">
                This changes the partner's capital balance. It cannot be undone.
            </flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

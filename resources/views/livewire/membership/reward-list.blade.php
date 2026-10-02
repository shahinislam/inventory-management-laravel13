<div class="p-4">

    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Member Rewards</flux:heading>
            <flux:text class="mt-1">Every customer is a member. Set spending ranges here, and the POS suggests the reward when a member qualifies. You decide at checkout whether to give it.</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('membership-rewards.create') }}" wire:navigate>New Reward</flux:button>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif

    {{-- At a glance --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Active rewards', $stats['active'], 'gift'],
            ['Times given', $stats['given'], 'check-badge'],
            ['Discount given', money($stats['discount']), 'receipt-percent'],
            ['Gift items given', $stats['gifts'], 'cube'],
        ] as [$label, $value, $icon])
            <flux:card class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-pink-50 dark:bg-pink-950/30">
                        <flux:icon :name="$icon" class="size-5 text-pink-600 dark:text-pink-400" />
                    </div>
                    <div>
                        <flux:text class="text-xs text-zinc-500">{{ $label }}</flux:text>
                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</div>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:card class="mb-6 p-4">
        <div class="flex items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search rewards..." icon="magnifying-glass" class="flex-1"
                autocomplete="one-time-code" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />
            <flux:select wire:model.live="basisFilter" class="w-52">
                <flux:select.option value="">All kinds</flux:select.option>
                <flux:select.option value="single_invoice">Per bill</flux:select.option>
                <flux:select.option value="cumulative">Total spend milestone</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    <flux:card>
        <flux:table :paginate="$rewards">
            <flux:table.columns>
                <flux:table.column>Reward</flux:table.column>
                <flux:table.column>When</flux:table.column>
                <flux:table.column>Member gets</flux:table.column>
                <flux:table.column>Given</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rewards as $reward)
                    <flux:table.row wire:key="{{ $reward->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $reward->name }}</flux:text>
                            <flux:badge size="sm" :color="$reward->isCumulative() ? 'amber' : 'blue'" class="mt-1">
                                {{ $reward->isCumulative() ? 'Milestone · once per member' : 'Per bill' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $reward->range_label }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$reward->isGift() ? 'pink' : 'green'"
                                :icon="$reward->isGift() ? 'gift' : 'receipt-percent'">{{ $reward->reward_label }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm tabular-nums">{{ $reward->redemptions_count }}×</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$reward->is_active ? 'green' : 'zinc'" class="cursor-pointer"
                                wire:click="toggleStatus({{ $reward->id }})" title="Click to switch">
                                {{ $reward->is_active ? 'Active' : 'Paused' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" :href="route('membership-rewards.edit', $reward)" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $reward->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="gift" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-500">No rewards yet</flux:text>
                                <flux:text class="max-w-md text-sm text-zinc-400">
                                    Example: “Bill of ৳5,000 or more → 5% off” or “Total spend reaches ৳50,000 → free gift”.
                                </flux:text>
                                <flux:button size="sm" variant="primary" href="{{ route('membership-rewards.create') }}" wire:navigate>Create your first reward</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Recently given --}}
    @if ($recent->isNotEmpty())
        <flux:card class="mt-6 p-6">
            <flux:heading class="mb-4">Recently given</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Member</flux:table.column>
                    <flux:table.column>Phone</flux:table.column>
                    <flux:table.column>Reward</flux:table.column>
                    <flux:table.column>Invoice</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($recent as $r)
                        <flux:table.row wire:key="recent-{{ $r->id }}">
                            <flux:table.cell>
                                @if ($r->customer)
                                    <a href="{{ route('customers.edit', $r->customer) }}" wire:navigate class="font-medium hover:underline">{{ $r->customer->name }}</a>
                                @else
                                    <span class="text-zinc-400">Deleted customer</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-sm">{{ $r->customer_phone }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:text class="text-sm">{{ $r->reward_name }}</flux:text>
                                <flux:text class="text-xs text-zinc-400">{{ $r->description }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($r->invoice)
                                    <a href="{{ route('invoices.show', $r->invoice) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $r->invoice->invoice_number }}</a>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-sm">{{ $r->created_at->format('d M Y, h:i A') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete reward?</flux:heading>
            <flux:text class="mt-2">It will no longer be offered at the POS. Rewards already given stay in each member's history. To stop it for a while instead, click its status to pause it.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

<div class="p-4">

    @php $canManage = auth()->user()->hasRole(['admin', 'manager']); @endphp

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payment Accounts</flux:heading>
            <flux:text class="mt-1">Bank accounts, cards and wallets used for payments</flux:text>
        </div>
        @if ($canManage)
            <flux:button icon="plus" href="{{ route('payment-accounts.create') }}" wire:navigate>
                Add Account
            </flux:button>
        @endif
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
                placeholder="Search by name, bank or number..."
                icon="magnifying-glass"
                class="flex-1" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />
            <flux:select wire:model.live="typeFilter" class="w-40">
                <flux:select.option value="">All Types</flux:select.option>
                @foreach (App\Models\PaymentAccount::TYPES as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="statusFilter" class="w-36">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
    </flux:card>

    {{-- Table --}}
    <flux:card>
        <flux:table :paginate="$accounts">
            <flux:table.columns>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Bank / Provider</flux:table.column>
                <flux:table.column>Holder</flux:table.column>
                <flux:table.column>Payments</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                @if ($canManage)
                    <flux:table.column align="end">Actions</flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse($accounts as $account)
                    <flux:table.row wire:key="{{ $account->id }}">
                        <flux:table.cell>
                            <flux:text class="font-medium">{{ $account->display_name }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" :color="match($account->type) { 'card' => 'purple', 'mobile_wallet' => 'pink', default => 'blue' }">
                                {{ $account->type_label }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $account->bank_name ?: '-' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $account->holder_name ?: '-' }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $account->payments_count + $account->purchase_payments_count }}</flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            @if ($canManage)
                                <flux:badge
                                    size="sm"
                                    :color="$account->is_active ? 'green' : 'red'"
                                    class="cursor-pointer"
                                    wire:click="toggleStatus({{ $account->id }})"
                                >{{ $account->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                            @else
                                <flux:badge size="sm" :color="$account->is_active ? 'green' : 'red'">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>

                        @if ($canManage)
                            <flux:table.cell align="end">
                                <flux:dropdown align="end">
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" :href="route('payment-accounts.edit', $account)" wire:navigate>Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $account->id }})">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="credit-card" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">No payment accounts found</flux:text>
                                @if ($canManage)
                                    <flux:button size="sm" href="{{ route('payment-accounts.create') }}" wire:navigate>
                                        Add your first account
                                    </flux:button>
                                @endif
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
            <flux:heading>Delete Payment Account</flux:heading>
            <flux:text class="mt-2">Past payments keep their account details. The account will no longer be offered at checkout.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

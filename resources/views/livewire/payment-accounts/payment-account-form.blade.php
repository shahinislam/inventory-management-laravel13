<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('payment-accounts.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->account?->exists ? 'Edit Payment Account' : 'Add Payment Account' }}</flux:heading>
            <flux:text class="mt-1">A bank account, card or mobile wallet that payments go through</flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

            {{-- Left Column --}}
            <div class="space-y-6">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Account Details</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Account Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="name" placeholder="e.g. City Bank Current, Company Visa" />
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Type</flux:label>
                                <flux:select wire:model="type">
                                    @foreach (App\Models\PaymentAccount::TYPES as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="type" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Bank / Provider</flux:label>
                                <flux:input wire:model="bank_name" placeholder="e.g. City Bank, bKash" />
                                <flux:error name="bank_name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Account / Card Number</flux:label>
                                <flux:input wire:model="account_number" placeholder="Full number or last 4 digits" />
                                <flux:error name="account_number" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Holder Name</flux:label>
                            <flux:input wire:model="holder_name" placeholder="Name on the account or card" />
                            <flux:error name="holder_name" />
                        </flux:field>
                    </div>
                </flux:card>

                <flux:card class="p-6">
                    <flux:heading class="mb-4">Notes</flux:heading>
                    <flux:textarea wire:model="notes" placeholder="Additional notes about this account..." rows="3" />
                    <flux:error name="notes" />
                </flux:card>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6">
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Status</flux:heading>
                    <flux:field>
                        <div class="flex items-center justify-between">
                            <flux:label>Active</flux:label>
                            <flux:switch wire:model="is_active" />
                        </div>
                    </flux:field>
                    <flux:text class="mt-2 text-xs text-zinc-400">Inactive accounts are hidden at checkout.</flux:text>
                </flux:card>

                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->account?->exists ? 'Update Account' : 'Save Account' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('payment-accounts.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </form>

</div>

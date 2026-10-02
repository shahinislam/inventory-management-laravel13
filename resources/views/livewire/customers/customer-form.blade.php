<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('customers.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->customer?->exists ? 'Edit Customer' : 'Add Customer' }}</flux:heading>
            <flux:text class="mt-1">{{ $this->customer?->exists ? 'Update customer details' : 'Every customer you add becomes a member' }}</flux:text>
        </div>
        @if ($this->customer?->exists)
            <flux:badge color="pink" icon="identification" class="ml-auto">Member · {{ $this->customer->member_no }}</flux:badge>
        @endif
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

            {{-- Left Column --}}
            <div class="space-y-6">

                {{-- Basic Info --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Basic Information</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Customer Name <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                            <flux:input wire:model="name" placeholder="Full name" />
                            <flux:error name="name" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input wire:model="email" type="email" placeholder="email@example.com" />
                                <flux:error name="email" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Phone <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="phone" type="tel" placeholder="01XXXXXXXXX" />
                                <flux:description>Used to find this member at checkout. Must be unique.</flux:description>
                                <flux:error name="phone" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Alternative Phone</flux:label>
                                <flux:input wire:model="alternative_phone" placeholder="Secondary number" />
                                <flux:error name="alternative_phone" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Date of Birth</flux:label>
                                <flux:input wire:model="date_of_birth" type="date" />
                                <flux:error name="date_of_birth" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Gender</flux:label>
                            <flux:select wire:model="gender" placeholder="Select gender">
                                <flux:select.option value="">Not specified</flux:select.option>
                                <flux:select.option value="male">Male</flux:select.option>
                                <flux:select.option value="female">Female</flux:select.option>
                                <flux:select.option value="other">Other</flux:select.option>
                            </flux:select>
                            <flux:error name="gender" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Address --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Address</flux:heading>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Street Address</flux:label>
                            <flux:textarea wire:model="address" placeholder="Street address" rows="2" />
                            <flux:error name="address" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>City</flux:label>
                                <flux:input wire:model="city" placeholder="City" />
                                <flux:error name="city" />
                            </flux:field>

                            <flux:field>
                                <flux:label>State / Division</flux:label>
                                <flux:input wire:model="state" placeholder="State or division" />
                                <flux:error name="state" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Country</flux:label>
                                <flux:input wire:model="country" placeholder="Country" />
                                <flux:error name="country" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Postal Code</flux:label>
                                <flux:input wire:model="postal_code" placeholder="Postal / ZIP code" />
                                <flux:error name="postal_code" />
                            </flux:field>
                        </div>
                    </div>
                </flux:card>

                {{-- Financial --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Financial Information</flux:heading>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>Tax Number</flux:label>
                            <flux:input wire:model="tax_number" placeholder="Tax ID (for business)" />
                            <flux:error name="tax_number" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Credit Limit</flux:label>
                            <flux:input wire:model="credit_limit" type="number" step="0.01" min="0" placeholder="0.00" prefix="৳" />
                            <flux:error name="credit_limit" />
                        </flux:field>
                    </div>
                </flux:card>

                {{-- Notes --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Notes</flux:heading>
                    <flux:textarea wire:model="notes" placeholder="Additional notes about this customer..." rows="3" />
                    <flux:error name="notes" />
                </flux:card>

            </div>

            {{-- Right Column --}}
            <div class="space-y-6">

                {{-- Photo --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Customer Photo</flux:heading>

                    @if($media)
                        <div class="relative">
                            <img src="{{ $media->file_url }}" class="w-full rounded-lg object-cover aspect-square" />
                            <flux:button
                                icon="x-mark" variant="ghost" size="sm" square
                                class="absolute top-2 right-2 bg-white dark:bg-zinc-800"
                                wire:click="removeMedia" type="button"
                            />
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="$set('showMediaPicker', true)"
                            class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 p-6 text-zinc-400 hover:border-zinc-400 dark:border-zinc-700"
                        >
                            <flux:icon name="photo" class="size-8" />
                            <flux:text class="text-sm">Click to select photo</flux:text>
                        </button>
                    @endif
                </flux:card>

                {{-- Stats (Edit only) --}}
                @if($this->customer?->exists)
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Membership</flux:heading>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Member No.</flux:text>
                            <flux:text class="font-mono font-medium">{{ $this->customer->member_no }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Member since</flux:text>
                            <flux:text class="font-medium">{{ $this->customer->created_at?->format('d M Y') }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Purchases</flux:text>
                            <flux:text class="font-medium">{{ $membership['purchases'] }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Total spent</flux:text>
                            <flux:text class="font-medium">{{ money($membership['spend']) }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Rewards received</flux:text>
                            <flux:text class="font-medium">{{ $this->customer->rewardRedemptions()->count() }}</flux:text>
                        </div>
                        @if ($membership['next'])
                            <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                {{ money($membership['next']['remaining']) }} more to unlock
                                <span class="font-semibold">{{ $membership['next']['reward']->reward_label }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <flux:text class="text-sm text-zinc-500">Current Balance</flux:text>
                            <flux:text class="font-medium {{ $this->customer->current_balance > 0 ? 'text-red-500' : '' }}">
                                {{ money($this->customer->current_balance) }}
                            </flux:text>
                        </div>
                    </div>
                </flux:card>
                @endif

                {{-- Status --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Status</flux:heading>
                    <flux:field>
                        <div class="flex items-center justify-between">
                            <flux:label>Active</flux:label>
                            <flux:switch wire:model="is_active" />
                        </div>
                    </flux:field>
                </flux:card>

                {{-- Actions --}}
                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" class="w-full" icon="check">
                            {{ $this->customer?->exists ? 'Update Customer' : 'Save Customer' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('customers.index') }}" wire:navigate>
                            Cancel
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

    {{-- Membership history (edit only) --}}
    @if ($membership)
        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <flux:card class="p-6">
                <flux:heading class="mb-4">Recent purchases</flux:heading>
                @if ($membership['invoices']->isEmpty())
                    <flux:text class="text-sm text-zinc-400">No purchases yet. Sales to this customer will show up here.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Invoice</flux:table.column>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Phone</flux:table.column>
                            <flux:table.column class="text-right">Total</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($membership['invoices'] as $inv)
                                <flux:table.row wire:key="inv-{{ $inv->id }}">
                                    <flux:table.cell>
                                        <a href="{{ route('invoices.show', $inv) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $inv->invoice_number }}</a>
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm">{{ $inv->invoice_date->format('d M Y') }}</flux:table.cell>
                                    <flux:table.cell class="text-sm">{{ $inv->customer_phone ?? '—' }}</flux:table.cell>
                                    <flux:table.cell class="text-right font-medium tabular-nums">{{ money($inv->total) }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            <flux:card class="p-6">
                <flux:heading class="mb-4">Rewards received</flux:heading>
                @if ($membership['redemptions']->isEmpty())
                    <flux:text class="text-sm text-zinc-400">No rewards given yet. Rewards are offered at the POS when this member qualifies.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Reward</flux:table.column>
                            <flux:table.column>Given</flux:table.column>
                            <flux:table.column>Invoice</flux:table.column>
                            <flux:table.column>Date</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($membership['redemptions'] as $r)
                                <flux:table.row wire:key="red-{{ $r->id }}">
                                    <flux:table.cell class="text-sm font-medium">{{ $r->reward_name }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$r->reward_type === 'gift' ? 'pink' : 'green'"
                                            :icon="$r->reward_type === 'gift' ? 'gift' : 'receipt-percent'">{{ $r->description }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($r->invoice)
                                            <a href="{{ route('invoices.show', $r->invoice) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $r->invoice->invoice_number }}</a>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-sm">{{ $r->created_at->format('d M Y') }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    @endif

    {{-- Media Picker Modal --}}
    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading class="mb-4">Select Photo</flux:heading>
            <livewire:media.media-picker wire:select-media="selectMedia" />
        </div>
    </flux:modal>

</div>

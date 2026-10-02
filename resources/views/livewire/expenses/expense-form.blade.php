<div class="p-4">

    <div class="mb-6 flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" href="{{ route('expenses.index') }}" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $this->expense?->exists ? 'Edit Expense' : 'Record Expense' }}</flux:heading>
            <flux:text class="mt-1">
                @if($this->expense?->exists)
                    {{ $this->expense->expense_number }} &middot; money spent running the shop
                @else
                    Money spent running the shop — rent, salaries, bills, transport and so on
                @endif
            </flux:text>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_20rem]">

            <div class="space-y-6">

                <flux:card class="p-6">
                    <flux:heading class="mb-4">What was it for?</flux:heading>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Category <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="category" list="expense-categories" placeholder="e.g. Rent" autocomplete="off" />
                                <datalist id="expense-categories">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}"></option>
                                    @endforeach
                                </datalist>
                                <flux:description>Pick a suggestion or type your own. Expenses are grouped by category in the profit &amp; loss.</flux:description>
                                <flux:error name="category" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Amount <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="amount" type="number" step="0.01" min="0.01" prefix="৳" placeholder="0.00" />
                                <flux:description>The total you paid.</flux:description>
                                <flux:error name="amount" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Date <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:input wire:model="expense_date" type="date" />
                                <flux:description>The day the money was spent.</flux:description>
                                <flux:error name="expense_date" />
                            </flux:field>
                            <flux:field>
                                <flux:label>Paid To</flux:label>
                                <flux:input wire:model="paid_to" placeholder="e.g. Landlord, DESCO, Rahim (cleaner)" />
                                <flux:description>Who received the money (optional).</flux:description>
                                <flux:error name="paid_to" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Notes</flux:label>
                            <flux:textarea wire:model="notes" rows="2" placeholder="Anything worth remembering, e.g. September rent" />
                            <flux:error name="notes" />
                        </flux:field>
                    </div>
                </flux:card>

                <flux:card class="p-6">
                    <flux:heading class="mb-4">How was it paid?</flux:heading>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>Payment Method <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                <flux:select wire:model.live="method">
                                    @foreach($methods as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="method" />
                            </flux:field>

                            @if($needsAccount)
                                <flux:field>
                                    <flux:label>Paid From Account <flux:badge color="red" size="sm">Required</flux:badge></flux:label>
                                    <flux:select wire:model="payment_account_id">
                                        <flux:select.option value="">Choose account</flux:select.option>
                                        @foreach($accounts as $account)
                                            <flux:select.option value="{{ $account->id }}">{{ $account->display_name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @if($accounts->isEmpty())
                                        <flux:description>No active account for this method yet — add one under Bank Accounts first.</flux:description>
                                    @else
                                        <flux:description>The bank account, card or wallet the money left from.</flux:description>
                                    @endif
                                    <flux:error name="payment_account_id" />
                                </flux:field>
                            @endif
                        </div>

                        @if($method !== 'cash')
                            <flux:field>
                                <flux:label>
                                    {{ $needsReference ? 'Transaction ID' : 'Reference' }}
                                    @if($needsReference)
                                        <flux:badge color="red" size="sm">Required</flux:badge>
                                    @endif
                                </flux:label>
                                <flux:input wire:model="reference" :placeholder="$needsReference ? 'e.g. 9A7B6C5D4E' : 'Cheque no., transfer ref...'" />
                                <flux:description>
                                    {{ $needsReference ? 'The bKash / Nagad transaction ID, so this payment can be traced.' : 'Optional — helps match this with your bank statement.' }}
                                </flux:description>
                                <flux:error name="reference" />
                            </flux:field>
                        @endif

                        @if($method === 'cash' && $shift)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:switch wire:model="fromTill" label="Paid in cash from my till" align="left" />
                                <flux:text class="mt-2 text-xs text-zinc-500">
                                    Turn on if you took this cash out of the drawer during your shift ({{ $shift->shift_number }}).
                                    The shift close will then expect that much less cash in the drawer.
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </flux:card>

            </div>

            <div class="space-y-6">

                <flux:card class="p-6">
                    <flux:heading class="mb-1">Receipt</flux:heading>
                    <flux:text class="mb-4 text-xs text-zinc-500">Optional — attach a photo of the bill or receipt.</flux:text>

                    @if($media)
                        <div class="relative">
                            @if($media->isImage())
                                <img src="{{ $media->file_url }}" alt="Receipt" class="w-full rounded-lg object-cover" />
                            @else
                                <a href="{{ $media->file_url }}" target="_blank" class="flex items-center gap-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                    <flux:icon name="document" class="size-5" /> {{ $media->file_name }}
                                </a>
                            @endif
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
                            <flux:text class="text-sm">Click to attach receipt</flux:text>
                        </button>
                    @endif
                </flux:card>

                <flux:card class="p-6">
                    <div class="space-y-3">
                        <flux:button type="submit" variant="primary" class="w-full" icon="check">
                            {{ $this->expense?->exists ? 'Update Expense' : 'Save Expense' }}
                        </flux:button>
                        <flux:button type="button" variant="ghost" class="w-full" href="{{ route('expenses.index') }}" wire:navigate>Cancel</flux:button>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>

    {{-- Media Picker Modal --}}
    <flux:modal wire:model="showMediaPicker" class="max-w-4xl">
        <div class="p-6">
            <flux:heading class="mb-4">Select Receipt</flux:heading>
            <livewire:media.media-picker wire:select-media="selectMedia" />
        </div>
    </flux:modal>

</div>

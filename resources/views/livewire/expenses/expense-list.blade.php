<div class="p-4">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Expenses</flux:heading>
            <flux:text class="mt-1">Money spent running the shop — rent, salaries, bills and more</flux:text>
        </div>
        <flux:button icon="plus" href="{{ route('expenses.create') }}" wire:navigate>Record Expense</flux:button>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-100 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ session('error') }}</div>
    @endif

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Spent this period" :value="money($summary['total'])" tone="red" icon="banknotes" :hint="$this->periodLabel()" />
        <x-stat-tile label="Expenses recorded" :value="number_format($summary['count'])" icon="receipt-percent" />
        <x-stat-tile
            label="Biggest category"
            :value="$summary['top_category'] ?? '—'"
            tone="yellow"
            icon="chart-pie"
            :hint="$summary['top_category'] ? money($summary['top_total']) : 'Nothing spent yet'"
        />
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, category, paid to, notes..." icon="magnifying-glass" class="min-w-56 flex-1" autocomplete="one-time-code" autocorrect="off" autocapitalize="off" spellcheck="false" name="q-{{ Str::random(10) }}" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other" />

            <flux:select wire:model.live="categoryFilter" class="w-44">
                <flux:select.option value="">All categories</flux:select.option>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="methodFilter" class="w-40">
                <flux:select.option value="">All methods</flux:select.option>
                @foreach($methods as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="period" class="w-40">
                @foreach($this->periodOptions() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($period === 'custom')
                <flux:input wire:model.live="dateFrom" type="date" class="w-40" />
                <flux:text>to</flux:text>
                <flux:input wire:model.live="dateTo" type="date" class="w-40" />
            @endif

            <flux:switch wire:model.live="showDrawerMoves" label="Show till cash in / out" align="left" />
        </div>
        @if($showDrawerMoves)
            <flux:text class="mt-3 text-xs text-zinc-500">Till cash in / out are drawer moves made from the shift screen. They are not costs and are not counted in the totals above or in profit &amp; loss.</flux:text>
        @endif
    </flux:card>

    <flux:card>
        <flux:table :paginate="$expenses">
            <flux:table.columns>
                <flux:table.column>Number</flux:table.column>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Paid To</flux:table.column>
                <flux:table.column>Method</flux:table.column>
                <flux:table.column>Account</flux:table.column>
                <flux:table.column class="text-right">Amount</flux:table.column>
                <flux:table.column class="text-right">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($expenses as $expense)
                    @php($isCost = $expense->type === 'expense')
                    <flux:table.row wire:key="expense-{{ $expense->id }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:text class="font-medium">{{ $expense->expense_number }}</flux:text>
                                @if($expense->media)
                                    <a href="{{ $expense->media->file_url }}" target="_blank" title="View receipt" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                                        <flux:icon name="paper-clip" class="size-4" />
                                    </a>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $expense->expense_date?->format('d M Y') }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($isCost)
                                <flux:badge size="sm" color="zinc">{{ $expense->category ?? '—' }}</flux:badge>
                            @else
                                <flux:badge size="sm" :color="$expense->type === 'cash_in' ? 'green' : 'amber'">{{ \App\Models\Expense::TYPES[$expense->type] }}</flux:badge>
                            @endif
                            @if($expense->shift_id)
                                <flux:text class="mt-1 text-xs text-zinc-400">From till ({{ $expense->shift?->shift_number ?? "shift #".$expense->shift_id }})</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $expense->paid_to ?: '—' }}</flux:text>
                            @if($expense->notes)
                                <flux:text class="text-xs text-zinc-400">{{ Str::limit($expense->notes, 40) }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ \App\Models\Payment::methodLabel($expense->method) }}</flux:text>
                            @if($expense->reference)
                                <flux:text class="text-xs text-zinc-400">Ref: {{ $expense->reference }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-sm">{{ $expense->paymentAccount?->display_name ?? '—' }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:text class="font-semibold tabular-nums {{ $isCost ? 'text-red-600 dark:text-red-400' : '' }}">{{ money($expense->amount) }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if($isCost)
                                <flux:dropdown align="end">
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" square />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" :href="route('expenses.edit', $expense)" wire:navigate>Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $expense->id }})">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @else
                                <flux:text class="text-xs text-zinc-400">Drawer move</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="receipt-percent" class="size-10 text-zinc-300" />
                                @if($search || $categoryFilter || $methodFilter)
                                    <flux:text class="text-zinc-400">No expenses match these filters</flux:text>
                                @else
                                    <flux:text class="text-zinc-400">No expenses yet in this period</flux:text>
                                    <flux:button size="sm" href="{{ route('expenses.create') }}" wire:navigate>Record your first expense</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="deleteId" class="max-w-sm">
        <div class="p-6">
            <flux:heading>Delete Expense</flux:heading>
            <flux:text class="mt-2">Delete this expense? It will no longer count in your profit &amp; loss.</flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('deleteId', null)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

</div>

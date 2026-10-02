<div class="p-4">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button icon="arrow-left" variant="ghost" :href="route('pos')" wire:navigate />
            <div>
                <flux:heading size="xl">{{ $shift ? 'Shift '.$shift->shift_number : 'My Shift' }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($shift)
                        {{ $shift->user->name }} · opened {{ $shift->opened_at->format('d M Y, h:i A') }}
                        @if ($shift->closed_at) · closed {{ $shift->closed_at->format('h:i A') }} @endif
                    @else
                        Cash drawer for your till
                    @endif
                </flux:text>
            </div>
        </div>
        @if ($shift)
            <div class="flex gap-2">
                <flux:badge :color="$shift->isOpen() ? 'green' : 'zinc'" :icon="$shift->isOpen() ? 'lock-open' : 'lock-closed'">
                    {{ $shift->isOpen() ? 'Open' : 'Closed' }}
                </flux:badge>
                <flux:button size="sm" variant="outline" icon="printer" :href="route('shifts.report', $shift)" target="_blank">Z-report</flux:button>
            </div>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-100 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-amber-100 p-4 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ session('error') }}</div>
    @endif

    @if (! $shift)
        <flux:card class="mx-auto max-w-md p-8 text-center">
            <flux:icon name="banknotes" class="mx-auto size-10 text-zinc-300" />
            <flux:heading class="mt-3">No open shift</flux:heading>
            <flux:text class="mt-1 text-sm">Open a shift from the POS by counting the cash in the drawer.</flux:text>
            <flux:button class="mt-4" variant="primary" icon="computer-desktop" :href="route('pos')" wire:navigate>Go to POS</flux:button>
        </flux:card>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                {{-- Cash drawer --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Cash in the drawer</flux:heading>
                    <div class="space-y-2 text-sm">
                        @foreach ([
                            ['Opening cash', $summary['opening_cash'], ''],
                            ['Cash sales', $summary['cash_sales'], '+'],
                            ['Cash refunds', $summary['cash_refunds'], '−'],
                            ['Cash added', $summary['cash_in'], '+'],
                            ['Cash taken out', $summary['cash_out'], '−'],
                            ['Expenses paid from drawer', $summary['cash_expenses'], '−'],
                        ] as [$label, $value, $sign])
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ $label }}</span>
                                <span class="tabular-nums">{{ $sign }}{{ money($value) }}</span>
                            </div>
                        @endforeach
                        <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700">
                            <span>Should be in drawer</span>
                            <span class="tabular-nums">{{ money($summary['expected_cash']) }}</span>
                        </div>
                        @if (! $shift->isOpen())
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Counted</span>
                                <span class="tabular-nums">{{ money($shift->counted_cash) }}</span>
                            </div>
                            <div class="flex justify-between font-semibold">
                                <span>Difference</span>
                                <span @class(['tabular-nums', 'text-green-600' => (float) $shift->difference >= 0, 'text-red-600' => (float) $shift->difference < 0])>
                                    {{ (float) $shift->difference > 0 ? '+' : '' }}{{ money($shift->difference) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </flux:card>

                {{-- All takings by method --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Takings by method</flux:heading>
                    @if (empty($summary['by_method']))
                        <flux:text class="text-sm text-zinc-400">No payments taken yet in this shift.</flux:text>
                    @else
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Method</flux:table.column>
                                <flux:table.column class="text-right">Payments</flux:table.column>
                                <flux:table.column class="text-right">Taken</flux:table.column>
                                <flux:table.column class="text-right">Refunded</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($summary['by_method'] as $row)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $row['label'] }}</flux:table.cell>
                                        <flux:table.cell class="text-right tabular-nums">{{ $row['count'] }}</flux:table.cell>
                                        <flux:table.cell class="text-right tabular-nums">{{ money($row['total']) }}</flux:table.cell>
                                        <flux:table.cell class="text-right tabular-nums">{{ $row['refunded'] > 0 ? '−'.money($row['refunded']) : '—' }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @endif
                    <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <div class="text-xs text-zinc-500">Sales</div>
                            <div class="font-semibold tabular-nums">{{ $summary['sales_count'] }} · {{ money($summary['sales_total']) }}</div>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <div class="text-xs text-zinc-500">Given on due</div>
                            <div class="font-semibold tabular-nums">{{ money($summary['due_given']) }}</div>
                        </div>
                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <div class="text-xs text-zinc-500">Returns</div>
                            <div class="font-semibold tabular-nums">{{ $summary['returns_count'] }} · {{ money($summary['returns_total']) }}</div>
                        </div>
                    </div>
                </flux:card>

                {{-- Drawer movements --}}
                <flux:card class="p-6">
                    <flux:heading class="mb-4">Cash added / taken out</flux:heading>
                    @forelse ($movements as $m)
                        <div wire:key="mv-{{ $m->id }}" class="flex items-center justify-between border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-800">
                            <div>
                                <div class="font-medium">{{ $m->notes ?: $m->category }}</div>
                                <div class="text-xs text-zinc-500">{{ App\Models\Expense::TYPES[$m->type] ?? $m->type }} · {{ $m->created_at->format('h:i A') }} · {{ $m->createdBy?->name }}</div>
                            </div>
                            <span @class(['font-semibold tabular-nums', 'text-green-600' => $m->type === 'cash_in', 'text-red-600' => $m->type !== 'cash_in'])>
                                {{ $m->type === 'cash_in' ? '+' : '−' }}{{ money($m->amount) }}
                            </span>
                        </div>
                    @empty
                        <flux:text class="text-sm text-zinc-400">Nothing yet.</flux:text>
                    @endforelse
                </flux:card>
            </div>

            @if ($shift->isOpen())
                <div class="space-y-6">
                    <flux:card class="p-6">
                        <flux:heading class="mb-1">Add or take cash</flux:heading>
                        <flux:text class="mb-4 text-sm">For change from the bank, a bank drop, or petty cash. Shop expenses go in Finance → Expenses.</flux:text>
                        <form wire:submit="moveCash" class="space-y-3">
                            <flux:radio.group wire:model="cashDirection" variant="segmented">
                                <flux:radio value="in" label="Add cash" icon="arrow-down-tray" />
                                <flux:radio value="out" label="Take cash" icon="arrow-up-tray" />
                            </flux:radio.group>
                            <flux:input wire:model="cashAmount" type="number" step="0.01" min="0" prefix="৳" placeholder="Amount" />
                            <flux:error name="cashAmount" />
                            <flux:input wire:model="cashReason" placeholder="Reason" />
                            <flux:error name="cashReason" />
                            <flux:button type="submit" class="w-full" icon="check">Save</flux:button>
                        </form>
                    </flux:card>

                    <flux:card class="p-6">
                        <flux:heading class="mb-1">Close shift</flux:heading>
                        <flux:text class="mb-4 text-sm">Count every note and coin in the drawer, then enter the total.</flux:text>
                        <form wire:submit="closeShift" class="space-y-3">
                            <flux:field>
                                <flux:label>Cash counted</flux:label>
                                <flux:input wire:model="countedCash" type="number" step="0.01" min="0" prefix="৳" class="text-lg tabular-nums" />
                                <flux:error name="countedCash" />
                            </flux:field>
                            <flux:textarea wire:model="closeNotes" rows="2" placeholder="Notes (optional)" />
                            <flux:button type="submit" variant="danger" class="w-full" icon="lock-closed"
                                wire:confirm="Close this shift? Sales stop until a new shift is opened.">Close shift</flux:button>
                        </form>
                    </flux:card>
                </div>
            @endif
        </div>
    @endif
</div>

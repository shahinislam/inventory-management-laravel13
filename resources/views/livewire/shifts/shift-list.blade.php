<div class="p-4">

    <div class="mb-6">
        <flux:heading size="xl">Shifts</flux:heading>
        <flux:text class="mt-1">Every cashier's till session, with the counted vs expected cash.</flux:text>
    </div>

    <flux:card class="mb-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="userFilter" class="w-48">
                <flux:select.option value="">All cashiers</flux:select.option>
                @foreach ($users as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="statusFilter" class="w-36">
                <flux:select.option value="">Any status</flux:select.option>
                <flux:select.option value="open">Open</flux:select.option>
                <flux:select.option value="closed">Closed</flux:select.option>
            </flux:select>
            <flux:input wire:model.live="dateFrom" type="date" class="w-40" />
            <flux:input wire:model.live="dateTo" type="date" class="w-40" />
        </div>
    </flux:card>

    <flux:card>
        <flux:table :paginate="$shifts">
            <flux:table.columns>
                <flux:table.column>Shift</flux:table.column>
                <flux:table.column>Cashier</flux:table.column>
                <flux:table.column>Opened</flux:table.column>
                <flux:table.column>Closed</flux:table.column>
                <flux:table.column class="text-right">Expected</flux:table.column>
                <flux:table.column class="text-right">Counted</flux:table.column>
                <flux:table.column class="text-right">Difference</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($shifts as $s)
                    <flux:table.row wire:key="shift-{{ $s->id }}">
                        <flux:table.cell>
                            <a href="{{ route('shifts.show', $s) }}" wire:navigate class="font-mono text-sm hover:underline">{{ $s->shift_number }}</a>
                            <flux:badge size="sm" :color="$s->isOpen() ? 'green' : 'zinc'" class="ml-1">{{ $s->isOpen() ? 'Open' : 'Closed' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $s->user?->name }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $s->opened_at->format('d M, h:i A') }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $s->closed_at?->format('d M, h:i A') ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ $s->expected_cash !== null ? money($s->expected_cash) : '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right tabular-nums">{{ $s->counted_cash !== null ? money($s->counted_cash) : '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if ($s->difference !== null)
                                <span @class(['font-semibold tabular-nums', 'text-green-600' => (float) $s->difference >= 0, 'text-red-600' => (float) $s->difference < 0])>
                                    {{ (float) $s->difference > 0 ? '+' : '' }}{{ money($s->difference) }}
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button size="xs" variant="ghost" icon="printer" :href="route('shifts.report', $s)" target="_blank" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <flux:icon name="banknotes" class="mx-auto size-10 text-zinc-300" />
                            <flux:text class="mt-2 text-zinc-400">No shifts yet. Cashiers open one from the POS.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>

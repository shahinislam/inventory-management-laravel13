<div class="p-4">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">SMS Log</flux:heading>
            <flux:text class="mt-1">Every text the shop has sent or tried to send. "Logged only" means the SMS gateway wasn't set up at the time.</flux:text>
        </div>
        <flux:button icon="paper-airplane" href="{{ route('marketing.sms') }}" wire:navigate>SMS to members</flux:button>
    </div>

    {{-- Summary --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Sent" :value="number_format($counts['sms_sent'] ?? 0)" tone="green" icon="check-circle" />
        <x-stat-tile label="Failed" :value="number_format($counts['sms_failed'] ?? 0)" tone="red" icon="x-circle" />
        <x-stat-tile label="Logged only" :value="number_format($counts['sms_logged'] ?? 0)" icon="document-text" />
    </div>

    {{-- Filters --}}
    <flux:card class="mb-6 p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr_1fr]">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search phone number..." icon="magnifying-glass" autocomplete="off" />
            <flux:select wire:model.live="status">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="sent">Sent</flux:select.option>
                <flux:select.option value="failed">Failed</flux:select.option>
                <flux:select.option value="logged">Logged only</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="type">
                <flux:select.option value="">All types</flux:select.option>
                @foreach($types as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <flux:card>
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>Time</flux:table.column>
                <flux:table.column>Phone</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Message</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Sent by</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($logs as $log)
                    @php
                        $v = $log->new_values ?? [];
                        $state = match ($log->action) { 'sms_sent' => 'sent', 'sms_failed' => 'failed', default => 'logged' };
                    @endphp
                    <flux:table.row wire:key="sms-{{ $log->id }}">
                        <flux:table.cell class="whitespace-nowrap text-sm" title="{{ $log->created_at?->format('d M Y H:i:s') }}">
                            {{ $log->created_at?->format('d M Y, h:i A') }}
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $v['phone'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="blue">{{ $types[$v['type'] ?? ''] ?? ucfirst($v['type'] ?? '—') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs">
                            <span class="block truncate text-sm" title="{{ $v['message'] ?? '' }}">{{ Str::limit($v['message'] ?? '', 60) }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($state === 'sent')
                                <flux:badge size="sm" color="green">Sent</flux:badge>
                            @elseif($state === 'failed')
                                <flux:tooltip :content="Str::limit($v['response'] ?? 'No response from the gateway', 200)">
                                    <flux:badge size="sm" color="red" icon="information-circle">Failed</flux:badge>
                                </flux:tooltip>
                            @else
                                <flux:badge size="sm" color="zinc">Logged only</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $log->user?->name ?? 'System' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="chat-bubble-left-right" class="size-10 text-zinc-300" />
                                <flux:text class="text-zinc-400">
                                    {{ ($status || $type || $search) ? 'No messages match these filters.' : 'No messages yet.' }}
                                </flux:text>
                                @unless($status || $type || $search)
                                    <flux:button size="sm" variant="outline" icon="paper-airplane" href="{{ route('marketing.sms') }}" wire:navigate>Send your first SMS</flux:button>
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

</div>

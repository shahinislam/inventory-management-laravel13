@props([
    'label' => '',
    'value' => null,
    'icon' => null,
    // 'default' | 'green' | 'yellow' | 'red' | 'blue' — semantic tone for the
    // figure. Every tone carries an explicit dark variant; several call sites
    // previously used bare text-green-600, which is unreadable on dark.
    'tone' => 'default',
    'hint' => null,
])

@php
    $toneClasses = match ($tone) {
        'green' => 'text-emerald-600 dark:text-emerald-400',
        'yellow' => 'text-amber-600 dark:text-amber-400',
        'red' => 'text-rose-600 dark:text-rose-400',
        'blue' => 'text-indigo-600 dark:text-indigo-400',
        default => 'text-zinc-900 dark:text-white',
    };

    // Icon sits in a tinted chip matching the tone, so a row of tiles reads as
    // colour-coded at a glance instead of a row of identical grey squares.
    $iconClasses = match ($tone) {
        'green' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        'yellow' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'red' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
        'blue' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
        default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
    };
@endphp

<div
    {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="truncate text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $label }}</div>

            {{-- tabular-nums keeps figures from jittering as values update. --}}
            <div class="mt-1.5 truncate text-2xl font-semibold tabular-nums tracking-tight {{ $toneClasses }}">
                {{ $value ?? $slot }}
            </div>

            @if ($hint)
                <div class="mt-1 truncate text-xs text-zinc-500">{{ $hint }}</div>
            @endif
        </div>

        @if ($icon)
            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $iconClasses }}">
                <flux:icon :name="$icon" class="size-4.5" />
            </div>
        @endif
    </div>
</div>

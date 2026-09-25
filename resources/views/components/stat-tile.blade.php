@props([
    'label' => '',
    'value' => null,
    'icon' => null,
    // 'default' | 'green' | 'yellow' | 'red' | 'blue' | 'purple' — tone for the
    // figure. Every tone carries an explicit dark variant.
    'tone' => 'default',
    'hint' => null,
])

@php
    // Full class strings (not interpolated) so Tailwind can see them.
    $theme = match ($tone) {
        'green' => [
            'card' => 'border-emerald-100 from-emerald-50 dark:border-emerald-500/20 dark:from-emerald-500/10',
            'glow' => 'bg-emerald-400/20',
            'icon' => 'bg-emerald-500 shadow-emerald-500/30',
            'value' => 'text-emerald-700 dark:text-emerald-300',
        ],
        'yellow' => [
            'card' => 'border-amber-100 from-amber-50 dark:border-amber-500/20 dark:from-amber-500/10',
            'glow' => 'bg-amber-400/20',
            'icon' => 'bg-amber-500 shadow-amber-500/30',
            'value' => 'text-amber-700 dark:text-amber-300',
        ],
        'red' => [
            'card' => 'border-rose-100 from-rose-50 dark:border-rose-500/20 dark:from-rose-500/10',
            'glow' => 'bg-rose-400/20',
            'icon' => 'bg-rose-500 shadow-rose-500/30',
            'value' => 'text-rose-700 dark:text-rose-300',
        ],
        'blue' => [
            'card' => 'border-indigo-100 from-indigo-50 dark:border-indigo-500/20 dark:from-indigo-500/10',
            'glow' => 'bg-indigo-400/20',
            'icon' => 'bg-indigo-500 shadow-indigo-500/30',
            'value' => 'text-indigo-700 dark:text-indigo-300',
        ],
        'purple' => [
            'card' => 'border-violet-100 from-violet-50 dark:border-violet-500/20 dark:from-violet-500/10',
            'glow' => 'bg-violet-400/20',
            'icon' => 'bg-violet-500 shadow-violet-500/30',
            'value' => 'text-violet-700 dark:text-violet-300',
        ],
        default => [
            'card' => 'border-zinc-200 from-zinc-50 dark:border-zinc-700/60 dark:from-zinc-800/60',
            'glow' => 'bg-zinc-400/15',
            'icon' => 'bg-zinc-800 shadow-zinc-800/20 dark:bg-zinc-600',
            'value' => 'text-zinc-900 dark:text-white',
        ],
    };
@endphp

<div {{ $attributes->class([
    'relative overflow-hidden rounded-2xl border bg-gradient-to-br print:hidden to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:to-zinc-900',
    $theme['card'],
]) }}>
    {{-- Soft decorative glow in the corner. --}}
    <div class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full blur-2xl {{ $theme['glow'] }}"></div>

    <div class="relative flex items-center gap-2.5">
        @if ($icon)
            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg text-white shadow-md {{ $theme['icon'] }}">
                <flux:icon :name="$icon" class="size-4" />
            </div>
        @endif
        <div class="min-w-0 text-xs font-medium uppercase leading-tight tracking-wider text-zinc-500 dark:text-zinc-400">
            {{ $label }}
        </div>
    </div>

    {{-- Full card width and allowed to wrap, so large amounts are never cut
         off. tabular-nums keeps figures from jittering as values update. --}}
    <div class="relative mt-3 break-words text-xl font-bold tabular-nums leading-tight tracking-tight sm:text-2xl {{ $theme['value'] }}">
        {{ $value ?? $slot }}
    </div>

    @if ($hint)
        <div class="relative mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</div>
    @endif
</div>

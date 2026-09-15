{{--
    One row in an <x-search-select> dropdown. Pass the Livewire action as
    wire:click; the keyboard highlight and click-to-close are wired here.
--}}
@props([
    'index',
    'label',
    'description' => null,
    'value' => null,
])

<button type="button" data-search-result
    x-on:click="open = false"
    x-on:mouseenter="highlight = {{ $index }}"
    x-bind:data-active="highlight === {{ $index }}"
    {{ $attributes->class('flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors data-[active=true]:bg-zinc-100 dark:data-[active=true]:bg-zinc-800') }}>
    @isset($leading)
        {{ $leading }}
    @endisset

    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</div>
        @if (filled($description))
            <div class="mt-0.5 truncate text-xs text-zinc-500">{{ $description }}</div>
        @endif
    </div>

    @if (filled($value))
        <span class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</span>
    @endif
</button>
